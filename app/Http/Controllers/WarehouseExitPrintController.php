<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WarehouseExitPrintController extends Controller
{
    /**
     * Mostra la pagina stampabile con le righe selezionate
     * dalla tabella uscite di magazzino.
     *
     * Questa stampa non genera DDT e non genera buoni:
     * stampa semplicemente la tabella con le righe selezionate.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $token
     * @return \Illuminate\Contracts\View\View
     */
    public function show(Request $request, string $token): View
    {
        /*
         * Controllo permesso lato controller.
         * La rotta è già protetta dal middleware permission:stock.exit,
         * ma questo guard rende il controller più sicuro anche se la rotta cambia.
         */
        abort_unless($request->user()?->can('stock.exit'), 403);

        /*
         * Recupera dalla cache gli ID selezionati da Livewire.
         * Il token viene passato tramite URL firmata temporanea.
         */
        $payload = Cache::get($this->cacheKey($token));

        abort_if(empty($payload), 404, 'La richiesta di stampa è scaduta o non è più disponibile.');

        /*
         * Impedisce a un utente di usare un token generato da un altro utente.
         */
        abort_unless(
            (int) ($payload['user_id'] ?? 0) === (int) $request->user()->id,
            403
        );

        /*
         * Normalizza gli ID delle righe selezionate.
         */
        $ids = collect($payload['ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        /*
         * Fase selezionata nella tabella Livewire al momento della stampa.
         */
        $phase = (int) ($payload['phase'] ?? 0);

        abort_if($ids->isEmpty(), 404, 'Nessuna riga selezionata.');

        /*
         * Sub-query identica al componente Livewire:
         * prende la quantità della riga nella fase corrente.
         */
        $pqSub = DB::table('v_order_item_phase_qty')
            ->select(
                'order_item_id',
                DB::raw('SUM(qty_in_phase) AS qty_in_phase')
            )
            ->where('phase', $phase)
            ->groupBy('order_item_id');

        /*
         * Query di stampa.
         *
         * Mantiene le stesse colonne della tabella Livewire:
         * Cliente, zona spedizione, nr. ordine, prodotto, data ordine,
         * consegna, valore e quantità in fase.
         */
        $rows = OrderItem::query()
            ->joinSub($pqSub, 'pq', 'pq.order_item_id', '=', 'order_items.id')
            ->join('orders as o', 'o.id', '=', 'order_items.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('occasional_customers as oc', 'oc.id', '=', 'o.occasional_customer_id')
            ->leftJoin('order_numbers as on', 'on.id', '=', 'o.order_number_id')
            ->leftJoin('products as p', 'p.id', '=', 'order_items.product_id')
            ->addSelect([
                'order_items.id',
                'order_items.order_id',
                'order_items.quantity',
                'order_items.unit_price',
                'pq.qty_in_phase',
                DB::raw('(order_items.quantity * order_items.unit_price) AS value'),
                DB::raw('COALESCE(c.company, oc.company) AS customer'),
                'on.number as order_number',
                'p.sku as product',
                'p.name as product_name',
                'o.ordered_at as order_date',
                'o.delivery_date',
                'o.shipping_zone as shipping_zone',
            ])
            ->whereIn('order_items.id', $ids)
            ->get()
            /*
             * Mantiene lo stesso ordine di selezione/visualizzazione ricevuto.
             */
            ->sortBy(fn ($row) => $ids->search((int) $row->id))
            ->values();

        /*
         * Se una riga non è più nella fase selezionata, non la stampiamo
         * silenziosamente: blocchiamo la stampa per evitare incoerenze.
         */
        abort_if(
            $rows->count() !== $ids->count(),
            404,
            'Una o più righe selezionate non sono più disponibili nella fase corrente.'
        );

        return view('pages.warehouse.exits-print-selected', [
            'rows'      => $rows,
            'phase'     => $phase,
            'phaseLabel' => $this->phaseLabel($phase),
            'printedAt' => Carbon::now(),
        ]);
    }

    /**
     * Restituisce la chiave cache usata per una richiesta di stampa.
     *
     * @param  string  $token
     * @return string
     */
    private function cacheKey(string $token): string
    {
        return "warehouse_exit_print:{$token}";
    }

    /**
     * Restituisce il nome umano della fase produttiva.
     *
     * @param  int  $phase
     * @return string
     */
    private function phaseLabel(int $phase): string
    {
        return match ($phase) {
            0 => 'Inserito',
            1 => 'Taglio',
            2 => 'Cucito',
            3 => 'Fusto',
            4 => 'Spugna',
            5 => 'Assemblaggio',
            6 => 'Spedizione',
            default => '—',
        };
    }
}