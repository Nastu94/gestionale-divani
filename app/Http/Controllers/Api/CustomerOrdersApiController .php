<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerOrdersApiController extends Controller
{
    /**
     * Ricerca ordini cliente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        /* ───────────────────── Validazione input minima ───────────────────── */
        $request->validate([
            'q'           => 'required|string|min:2',
            'customer_id' => 'nullable|integer|min:1',
            'limit'       => 'nullable|integer|min:1|max:50',
        ]);

        $limit = (int) ($request->input('limit', 20));
        if ($limit < 1)   $limit = 20;
        if ($limit > 50)  $limit = 50;

        /* ───────────────────── Normalizzazione termine ───────────────────── */
        $term = strtolower(trim((string) $request->input('q')));
        $term = str_replace('*', '%', $term);       // wildcard stile utente
        $term = preg_replace('/\s+/', '%', $term);  // spazi → '%'
        $like = "%{$term}%";

        $customerId = $request->integer('customer_id') ?: null;

        /* ─────────────────────────── Query base ────────────────────────────
         * - Seleziona solo ordini con order_numbers.order_type='customer'
         * - Colonne minime per il modale (id, number, date, customer info)
         */
        $q = Order::query()
            ->select([
                'orders.id',
                'orders.customer_id',
                'orders.ordered_at',
                'orders.delivery_date',
                'orders.shipping_address',
                'order_numbers.number',
                'customers.company',
                'customers.name as customer_name',
            ])
            ->leftJoin('order_numbers', 'order_numbers.id', '=', 'orders.order_number_id')
            ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('order_numbers.order_type', '=', 'customer');

        /* ─────────────────────────── Filtro cliente ───────────────────────── */
        if ($customerId) {
            $q->where('orders.customer_id', '=', $customerId);
        }

        /* ─────────────────────── Ricerca per testo ──────────────────────────
         * Cerca:
         *  - numero ordine (cast implicito LIKE su integer)
         *  - ragione sociale (customers.company) o nome (customers.name)
         */
        $q->where(function ($qq) use ($like) {
            $qq->orWhere('order_numbers.number', 'like', $like)
               ->orWhereRaw('LOWER(COALESCE(customers.company, "")) LIKE ?', [$like])
               ->orWhereRaw('LOWER(COALESCE(customers.name, "")) LIKE ?',    [$like]);
        });

        /* ─────────────────────── Ordinamento e limite ─────────────────────── */
        $orders = $q->orderByDesc('orders.ordered_at')
                    ->orderByDesc('orders.id')
                    ->limit($limit)
                    ->get();

        /* ─────────────────────── Mapping di output ──────────────────────────
         * Formattiamo le date come dd/mm/YYYY (coerente con le tue viste)
         * e incapsuliamo i dati cliente nei soli campi che il modale usa.
         */
        $out = $orders->map(function (Order $o) {
            // Nota: se i cast del modello formattano le date, qui le normalizziamo esplicitamente
            $orderedAt    = $o->ordered_at ? $o->ordered_at->format('d/m/Y') : null;
            $deliveryDate = $o->delivery_date ? $o->delivery_date->format('d/m/Y') : null;

            return [
                'id'            => $o->id,
                'number'        => $o->getAttribute('number'),   // via relazione orderNumber
                'ordered_at'    => $orderedAt,
                'delivery_date' => $deliveryDate,
                'customer'      => [
                    'id'               => $o->customer_id,
                    'company'          => $o->company ?? null,
                    'name'             => $o->customer_name ?? null,
                    'shipping_address' => $o->shipping_address ?? null,
                ],
            ];
        })->values();

        return response()->json($out);
    }
}
