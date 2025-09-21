<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerOrdersApiController extends Controller
{
    /**
     * Ricerca ordini cliente (autocomplete modale Resi).
     *
     * Parametri:
     *  - q: string|min:2     → filtro testuale (numero ordine o cliente)
     *  - limit: int<=50      → max risultati
     *  - customer_id: int    → (OPZ) filtra ordini del cliente standard
     *  - occasional_customer_id: int → (OPZ) filtra ordini del cliente occasionale
     */
    public function search(Request $request): JsonResponse
    {
        /* ── Validazione input ───────────────────────────────────────────── */
        $request->validate([
            'q'                     => 'required|string|min:2',
            'limit'                 => 'nullable|integer|min:1|max:50',
            'customer_id'           => 'nullable|integer|min:1',
            'occasional_customer_id'=> 'nullable|integer|min:1',
        ]);

        $limit = (int) min(max((int) $request->input('limit', 20), 1), 50);

        // Normalizzazione termine
        $term = strtolower(trim((string) $request->input('q')));
        $term = str_replace('*', '%', $term);        // wildcard stile utente
        $term = preg_replace('/\s+/', '%', $term);   // spazi → '%'
        $like = "%{$term}%";

        // Filtri cliente
        $customerId    = $request->integer('customer_id') ?: null;
        $occCustomerId = $request->integer('occasional_customer_id') ?: null;

        /* ── Query base ─────────────────────────────────────────────────── */
        $q = Order::query()
            ->select([
                'orders.id',
                'orders.customer_id',
                'orders.occasional_customer_id',
                'orders.ordered_at',
                'orders.delivery_date',
                'orders.shipping_address',

                'order_numbers.number        as order_number',
                'order_numbers.order_type    as order_type',

                'customers.company           as customer_company',
                'customers.email             as customer_email',

                'occasional_customers.company as occ_company',
                'occasional_customers.email   as occ_email',
                'occasional_customers.address',
                'occasional_customers.postal_code',
                'occasional_customers.city',
                'occasional_customers.province',
                'occasional_customers.country',
            ])
            ->leftJoin('order_numbers', 'order_numbers.id', '=', 'orders.order_number_id')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->leftJoin('occasional_customers', 'occasional_customers.id', '=', 'orders.occasional_customer_id')
            ->where('order_numbers.order_type', '=', 'customer'); // solo ordini cliente

        /* ── Filtro per cliente, se passato ─────────────────────────────── */
        if ($customerId) {
            $q->where('orders.customer_id', '=', $customerId);
        } elseif ($occCustomerId) {
            $q->where('orders.occasional_customer_id', '=', $occCustomerId);
        }

        /* ── Ricerca testuale: numero ordine + ragione sociale ──────────── */
        $q->where(function ($qq) use ($like) {
            $qq->orWhere('order_numbers.number', 'like', $like)
               ->orWhereRaw('LOWER(COALESCE(customers.company, "")) LIKE ?', [$like])
               ->orWhereRaw('LOWER(COALESCE(occasional_customers.company, "")) LIKE ?', [$like]);
        });

        /* ── Ordinamento + limite ───────────────────────────────────────── */
        $rows = $q->orderByDesc('orders.ordered_at')
                  ->orderByDesc('orders.id')
                  ->limit($limit)
                  ->get();

        /* ── Output compatibile con il modale ───────────────────────────── */
        $out = $rows->map(function ($o) {
            $orderedAt    = $o->ordered_at ? $o->ordered_at->format('d/m/Y') : null;
            $deliveryDate = $o->delivery_date ? $o->delivery_date->format('d/m/Y') : null;

            // Cliente standard vs occasionale
            if ($o->occasional_customer_id) {
                $addr = collect([
                    $o->address,
                    trim(($o->postal_code ? $o->postal_code.' ' : '').($o->city ?? '')),
                    $o->province,
                    $o->country,
                ])->filter()->implode(', ');

                $customer = [
                    'id'               => (int) $o->occasional_customer_id,
                    'company'          => $o->occ_company,
                    'email'            => $o->occ_email,
                    'shipping_address' => $addr ?: null,
                    'source'           => 'occasional',
                ];
            } else {
                $customer = [
                    'id'               => (int) $o->customer_id,
                    'company'          => $o->customer_company,
                    'email'            => $o->customer_email,
                    'shipping_address' => $o->shipping_address,
                    'source'           => 'customer',
                ];
            }

            return [
                'id'            => (int) $o->id,
                'number'        => (int) $o->order_number,  // numero progressivo (da order_numbers)
                'ordered_at'    => $orderedAt,
                'delivery_date' => $deliveryDate,
                'customer'      => $customer,
            ];
        })->values();

        return response()->json($out);
    }
}
