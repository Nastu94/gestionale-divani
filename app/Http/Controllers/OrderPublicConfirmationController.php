<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\InventoryServiceExtensions;
use App\Services\ProcurementService;
use App\Mail\Orders\OrderConfirmationRequestMail;
use App\Mail\Orders\OrderConfirmationOutcomeMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

/**
 * Controller pubblico per la conferma/rifiuto ordini STANDARD.
 * - Token monouso (uuid) con TTL (configurable).
 * - Pagina i18n con riepilogo e CTA conferma/rifiuta.
 * - Alla conferma: status=1 + confirmed_at; se consegna <30gg → crea PO (solo aggiuntivi).
 * - Al rifiuto: status=0 + reason obbligatoria.
 * - In entrambi i casi: email ai commerciali (ruoli configurati).
 */
class OrderPublicConfirmationController extends Controller
{
    /** TTL (giorni) del link pubblico, configurabile via config/orders.php */
    protected function ttlDays(): int
    {
        return (int) config('orders.confirmation_link_ttl_days', 14);
    }

    /** Recupera ordine da token valido (o null se scaduto/non valido) */
    protected function findValidByToken(string $token, bool $lock = false): ?Order
    {
        $min = now()->subDays($this->ttlDays());
        return Order::query()
            ->where('confirm_token', $token)
            ->whereNotNull('confirmation_requested_at')
            ->where('confirmation_requested_at', '>=', $min)
            ->whereNull('occasional_customer_id')   // solo STANDARD
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    /** Utenti destinatari notifica “commerciale” (ruoli configurabili) */
    protected function salesRecipients()
    {
        $roles = (array) config('orders.sales_roles', ['commerciale']);
        /** @var \App\Models\User $user */
        return \App\Models\User::role($roles)->get(); // spatie/permission
    }

    /** GET /orders/customer/confirm/{token} – Riepilogo pubblico */
    public function show(Request $request, string $token)
    {
        $order = $this->findValidByToken($token);

        if (!$order) {
            // Token scaduto o non valido → pagina “expired”
            return view('pages.orders.public.confirm', [
                'order'      => null,
                'token'      => $token,
                'expired'    => true,
                'ttl_days'   => $this->ttlDays(),
            ]);
        }

        // Locale preferito salvato al momento dell’invio (fallback app locale)
        if ($order->confirm_locale) {
            app()->setLocale($order->confirm_locale);
        }

        return view('pages.orders.public.confirm', [
            'order'    => $order->load(['items', 'items.product', 'items.variable']),
            'token'    => $token,
            'expired'  => false,
            'ttl_days' => $this->ttlDays(),
        ]);
    }

    /** POST /orders/customer/confirm/{token}/accept – Conferma cliente */
    public function confirm(Request $request, string $token)
    {
        $order = $this->findValidByToken($token);
        if (!$order) {
            return $this->respond($request, [
                'ok'      => false,
                'message' => __('orders.confirm.link_expired'),
            ], 410);
        }

        // Idempotenza: se già confermato, rispondi 200 senza rifare nulla
        if ((int)$order->status === 1) {
            return $this->respond($request, [
                'ok'      => true,
                'message' => __('orders.confirm.already_confirmed'),
            ]);
        }

        // PREFLIGHT CHECK
        $resolver = app(\App\Services\TessuComponentResolver::class);
        $order->load(['items.product', 'items.variable']);
        foreach ($order->items as $item) {
            $product = $item->product;
            if ($product && $resolver->tessuSlot($product)) {
                $fabricId = $item->variable?->fabric_id;
                $colorId = $item->variable?->color_id;
                $resolvedId = $item->variable?->resolved_component_id;

                try {
                    $resolver->resolveForStoredLine($product, $resolvedId, $fabricId, $colorId);
                } catch (\Exception $e) {
                    return $this->respond($request, [
                        'ok'      => false,
                        'message' => "L'ordine non può essere confermato: il prodotto '{$product->name}' ha una configurazione TESSU non valida. Contattare l'assistenza.",
                    ], 422);
                }
            }
        }

        $order = DB::transaction(function () use ($token) {
            /** Rilegge e blocca il token: un'altra richiesta potrebbe averlo consumato dopo il controllo iniziale. */
            $locked = $this->findValidByToken($token, true);
            if (!$locked || (int) $locked->status === 1) {
                return null;
            }

            $locked->status                    = 1;
            $locked->confirmed_at              = now();
            $locked->reason                    = null;
            $locked->confirm_token             = null;
            $locked->confirmation_requested_at = null;
            $locked->save();

            return $locked;
        });

        if (!$order) {
            return $this->respond($request, [
                'ok' => false,
                'message' => __('orders.confirm.link_expired'),
            ], 410);
        }

        $poNumbers = [];

        try {
            $confirmedAt = Carbon::parse($order->confirmed_at);
            $deliveryAt  = Carbon::parse($order->delivery_date);
            $daysDiff    = $confirmedAt->diffInDays($deliveryAt, false);
            $eligible    = $daysDiff >= 0 && $daysDiff < 30;

            if ($eligible) {
                $order->load(['items.variable']);

                $usedLines = $order->items->map(function ($item) {
                    return [
                        'id'            => $item->id, // serve a InventoryService per capire che è storico
                        'order_item_id' => $item->id,
                        'product_id'    => $item->product_id,
                        'quantity'      => (float) $item->quantity,
                        'fabric_id'     => $item->variable?->fabric_id,
                        'color_id'      => $item->variable?->color_id,
                        'resolved_component_id' => $item->variable?->resolved_component_id,
                    ];
                })->values()->all();

                $inventory = InventoryService::forDelivery(
                    $order->delivery_date,
                    $order->id
                )->check($usedLines);

                if ($inventory && ! $inventory->ok) {
                    $shortage = ProcurementService::buildShortageCollection(
                        $inventory->shortage
                    );

                    $result = ProcurementService::fromShortage(
                        $shortage,
                        $order->id
                    );

                    $poNumbers = $result['po_numbers']->all();
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Public confirm – procurement error', [
                'order_id' => $order->id,
                'error'    => $exception->getMessage(),
            ]);
        }

        $emailErrors = [];

        try {
            $salesEmails = $this->salesRecipients()
                ->pluck('email')
                ->filter()
                ->unique();

            foreach ($salesEmails as $email) {
                try {
                    Mail::to($email)->send(
                        new OrderConfirmationOutcomeMail(
                            order: $order->fresh(),
                            accepted: true,
                            poNumbers: $poNumbers,
                            reason: null,
                        )
                    );
                } catch (\Throwable $exception) {
                    $emailErrors[] = $email;

                    Log::error('Invio conferma al commerciale fallito', [
                        'order_id' => $order->id,
                        'email'    => $email,
                        'error'    => $exception->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            Log::error('Recupero destinatari commerciali fallito', [
                'order_id' => $order->id,
                'error'    => $exception->getMessage(),
            ]);
        }

        $customerEmail = $order->customer?->email;

        if ($customerEmail) {
            try {
                Mail::to($customerEmail)->send(
                    new OrderConfirmationOutcomeMail(
                        order: $order->fresh(),
                        accepted: true,
                        poNumbers: [],
                        reason: null,
                    )
                );
            } catch (\Throwable $exception) {
                $emailErrors[] = $customerEmail;

                Log::error('Invio conferma al cliente fallito', [
                    'order_id' => $order->id,
                    'email'    => $customerEmail,
                    'error'    => $exception->getMessage(),
                ]);
            }
        }

        return $this->respond($request, [
            'ok'      => true,
            'message' => __('orders.confirm.success') . (!empty($emailErrors) ? ' (Tuttavia, si è verificato un errore durante l\'invio di alcune email di riepilogo)' : ''),
        ]);
    }

    /** POST /orders/customer/confirm/{token}/reject – Rifiuto cliente */
    public function reject(Request $request, string $token)
    {
        $order = $this->findValidByToken($token);
        if (!$order) {
            return $this->respond($request, [
                'ok'      => false,
                'message' => __('orders.confirm.link_expired'),
            ], 410);
        }

        $data = $request->validate([
            'reason' => ['required','string','max:1000'],
        ]);

        $order = DB::transaction(function () use ($token, $data) {
            /** Il blocco condiviso con la conferma impedisce di usare due volte lo stesso link. */
            $locked = $this->findValidByToken($token, true);
            if (!$locked || (int) $locked->status === 1) {
                return null;
            }

            $locked->status                    = 0;
            $locked->reason                    = $data['reason'];
            $locked->confirm_token             = null;
            $locked->confirmation_requested_at = null;
            $locked->save();

            $number = $locked->orderNumber?->number ?? $locked->id;
            $customer = $locked->customer?->company ?: 'Cliente non indicato';

            /** Rifiuto e avviso vengono salvati insieme; il token originale non viene conservato nell'alert. */
            Alert::firstOrCreate(
                ['dedupe_key' => 'order_rejected:'.$locked->id.':'.hash('sha256', $token)],
                [
                    'type' => 'order_rejected',
                    'message' => "L'ordine #{$number} per {$customer} è stato rifiutato. Motivo: ".$data['reason'],
                    'payload' => [
                        'order_id' => $locked->id,
                        'order_number' => $number,
                        'customer' => $customer,
                        'reason' => $data['reason'],
                    ],
                    'is_read' => false,
                    'triggered_at' => now(),
                ]
            );

            return $locked;
        });

        if (!$order) {
            return $this->respond($request, [
                'ok' => false,
                'message' => __('orders.confirm.link_expired'),
            ], 410);
        }

        // Dopo commit: email ai commerciali e al cliente con la motivazione
        DB::afterCommit(function () use ($order, $data) {
            try {
                $emails = $this->salesRecipients()->pluck('email')->filter()->toArray();
                if ($order->customer && $order->customer->email) {
                    $emails[] = $order->customer->email;
                }
                foreach (array_unique($emails) as $email) {
                    Mail::to($email)->send(new OrderConfirmationOutcomeMail(
                        order: $order->fresh(),
                        accepted: false,
                        poNumbers: [],
                        reason: $data['reason']
                    ));
                }
            } catch (\Throwable $e) {
                Log::error('Public reject – notify error', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        });

        return $this->respond($request, [
            'ok'      => true,
            'message' => __('orders.confirm.rejected_ok'),
        ]);
    }

    /**
     * Risposta HTML (view) o JSON in base all'header Accept.
     * - HTML: re-render della view con banner di esito.
     * - JSON: contract minimo { ok, message }.
     */
    protected function respond(Request $req, array $payload, int $status = 200)
    {
        if ($req->expectsJson()) {
            return response()->json($payload, $status);
        }

        // Per HTML: mostriamo la stessa pagina con banner esito
        return response()->view('pages.orders.public.confirm-result', $payload + [
            'ttl_days' => $this->ttlDays(),
        ], $status);
    }
}
