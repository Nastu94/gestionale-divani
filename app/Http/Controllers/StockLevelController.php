<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\StockLevelLot;
use App\Models\Warehouse;
use App\Models\StockMovement;
use App\Models\LotNumber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StockLevelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Elenco ordini fornitore che attendono ancora la registrazione a stock.
     *
     * Mostra solo:
     *  • ordini con supplier_id valorizzato (≠ null)
     *
     * @return \Illuminate\View\View
     */
    public function indexEntry()
    {
        $supplierOrders = Order::with([
                'supplier',
                'orderNumber',
                'items.component',
                'stockLevelLots',
            ])
            ->whereNotNull('supplier_id')        // ordine di acquisto
            ->orderBy('delivery_date', 'asc')
            ->paginate(15);

        return view('pages.warehouse.entry', compact('supplierOrders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Registra (o aggiorna) un carico di magazzino per un ordine fornitore.
     *
     * Richiesta AJAX dal modale:
     *  - order_id        : id ordine fornitore (nullable in create-mode)
     *  - component_code  : codice del componente (es. CMP-001)
     *  - qty_received    : quantità caricata
     *  - lot_supplier    : lotto fornitore (string)
     *  - internal_lot_code    : lotto interno (facoltativo: viene generato se vuoto)
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeEntry(Request $request): JsonResponse
    {

        /* 1. Validazione ------------------------------------------------ */
        $data = $request->validate([
            'order_id'          => 'nullable|exists:orders,id',
            'component_code'    => 'required|string|exists:components,code',
            'qty_received'      => 'required|numeric|min:0.01',
            'lot_supplier'      => 'nullable|string|max:50',
            'internal_lot_code' => 'required|string|max:50',
        ], [
            'internal_lot_code.required' => 'Inserisci o genera il lotto interno.',
        ]);
        
        /* 0. Conferma lotto prenotato ----------------------------------- */
        $lotNumber = LotNumber::where('code', $data['internal_lot_code'])
            ->where('status', 'reserved')
            ->first();

        if (! $lotNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Lotto già utilizzato o non prenotato.',
            ], 422);
        }

        /* 2. Lookup component & magazzino ------------------------------- */
        $component = Component::where('code', $data['component_code'])->firstOrFail();
        $warehouse = Warehouse::firstWhere('code', 'MG-STOCK');

        /* 3. Recupera/crea blocco StockLevel --------------------------- */
        $stockLevel = StockLevel::firstOrCreate(
            [
                'component_id' => $component->id,
                'warehouse_id' => $warehouse->id,
            ]
        );

        /* 4. Crea o aggiorna il lotto specifico ------------------------ */
        $lot = $stockLevel->lots()->firstOrNew([
            'internal_lot_code' => $data['internal_lot_code'],
        ]);

        // se duplicato → errore leggibile
        if ($lot->exists) {
            return response()->json([
                'success' => false,
                'message' => 'Questo lotto interno è già stato registrato.',
            ], 422);
        }

        $lot->supplier_lot_code = $data['lot_supplier'] ?? null;
        $lot->quantity          = $data['qty_received'];
        $lot->save();
        
        $lot->lot_number_id = $lotNumber->id;
        $lot->save();

        /* segna come confermato */
        $lotNumber->update([
            'status'            => 'confirmed',
            'stock_level_lot_id'=> $lot->id,
        ]);

        /* 5. Mantieni in sync la quantità aggregata -------------------- */
        $stockLevel->quantity = $stockLevel->total_quantity;
        $stockLevel->save();

        /* 6. Collega all’ordine (pivot) -------------------------------- */
        if ($data['order_id']) {

            $order = Order::where('id', $data['order_id'])
                        ->whereHas('orderNumber', fn($q) => $q->where('order_type', 'supplier'))
                        ->first();

            if (! $order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ordine fornitore non trovato o non valido.',
                ], 422);
            }

            $order->stockLevelLots()->syncWithoutDetaching($stockLevel->lots->pluck('id'));
        }

        /* 7. Log movimento --------------------------------------------- */
        StockMovement::create([
            'stock_level_id' => $stockLevel->id,
            'type'           => 'IN',
            'quantity'       => $data['qty_received'],
            'note'           => 'Carico lotto interno ' . $lot->internal_lot_code,
        ]);

        /* 8. Risposta --------------------------------------------------- */
        return response()->json([
            'success' => true,
            'lot'     => $lot->only(['internal_lot_code','supplier_lot_code','quantity']),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(StockLevel $stockLevel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StockLevel $stockLevel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StockLevel $stockLevel)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StockLevel $stockLevel)
    {
        //
    }
}
