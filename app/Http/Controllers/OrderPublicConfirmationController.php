<?php

namespace App\Http\Controllers;

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
    protected function findValidByToken(string $token): ?Order
    {
        $min = now()->subDays($this->ttlDays());
        return Order::query()
            ->where('confirm_token', $token)
            ->whereNotNull('confirmation_requested_at')
            ->where('confirmation_requested_at', '>=', $min)
            ->whereNull('occasional_customer_id')   // solo STANDARD
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

        DB::transaction(function () use ($order) {
            // Stato confermato
            $order->status       = 1;
            $order->confirmed_at = now();
            $order->reason       = null;           // coerente con conferma
            // Invalida token (monouso)
            $order->confirm_token             = null;
            $order->confirmation_requested_at = null;
            $order->save();
        });

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
                        'product_id' => $item->product_id,
                        'quantity'   => (float) $item->quantity,
                        'fabric_id'  => $item->variable?->fabric_id,
                        'color_id'   => $item->variable?->color_id,
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

        DB::transaction(function () use ($order, $data) {
            // Stato non confermato; salviamo motivazione rifiuto
            $order->status = 0;
            $order->reason = $data['reason'];
            // Invalida token (monouso)
            $order->confirm_token             = null;
            $order->confirmation_requested_at = null;
            $order->save();
        });

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
        return view('pages.orders.public.confirm-result', $payload + [
            'ttl_days' => $this->ttlDays(),
        ]);
    }
}
