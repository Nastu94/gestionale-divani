<?php

namespace App\Http\Controllers;

use App\Models\StockLevel;
use App\Models\Order;
use App\Models\Component;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Illuminate\Http\Request;

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
                'stockLevels',
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
    public function storeEntry(Request $request)
    {
        /* VALIDAZIONE --------------------------------------------------- */
        $data = $request->validate([
            'order_id'            => 'nullable|exists:orders,id',
            'component_code'      => 'required|string|exists:components,code',
            'qty_received'        => 'required|numeric|min:0.01',
            'lot_supplier'        => 'nullable|string|max:50',
            'internal_lot_code'   => 'nullable|string|max:50',
        ],
        [   //  ⬅️  messaggi custom (chiave: <campo>.<regola>)
            'component_code.required'    => 'Seleziona un componente.',
            'qty_received.required'      => 'Inserisci la quantità ricevuta.',
            'lot_supplier.required'      => 'Inserisci il lotto fornitore.',
            'internal_lot_code.required' => 'Genera o inserisci il lotto interno.',
        ]);

        /* INDIVIDUA COMPONENTE / DEPOSITO ------------------------------ */
        $component = Component::where('code', $data['component_code'])->first();

        // decidi tu da dove recuperare il deposito (es. default o scelto nel form)
        $warehouse = Warehouse::firstWhere('code', 'MG-STOCK');

        /* CREA (o aggiorna) STOCK_LEVEL -------------------------------- */
        $stockLevel = new StockLevel;
        $stockLevel->component_id       = $component->id;
        $stockLevel->warehouse_id       = $warehouse->id;
        $stockLevel->quantity           = $data['qty_received'];
        $stockLevel->supplier_lot_code  = $data['lot_supplier'] ?? null;

        // Se il lotto interno non è stato passato generiamone uno nuovo
        if (! filled($data['internal_lot_code'])) {
            $stockLevel->generateLot();          // trait GeneratesLot
        } else {
            $stockLevel->internal_lot_code = $data['internal_lot_code'];
        }

        /* controllo duplicato  ----------------------------------------- */
        $duplicate = StockLevel::where([
                'component_id'       => $stockLevel->component_id,
                'warehouse_id'       => $stockLevel->warehouse_id,
                'internal_lot_code'  => $stockLevel->internal_lot_code,
        ])->exists();

        if ($duplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Questa riga è già stata registrata (lotto interno duplicato).',
            ], 422);
        }
        
        $stockLevel->save();

        /* LEGA ALL’ORDINE (se presente) ------------------------------- */
        if ($data['order_id']) {
            $order = Order::find($data['order_id']);

            // evita duplicati se la stessa riga viene registrata più volte
            $order->stockLevels()->syncWithoutDetaching($stockLevel->id);
            
            $order->save();
        }

        /* LOG MOVIMENTO (opzionale ma utile) --------------------------- */
        StockMovement::create([
            'stock_level_id' => $stockLevel->id,
            'type'           => 'IN',                 // carico
            'quantity'       => $data['qty_received'],
            'note'           => 'Carico ordine forn. #' . ($data['order_id'] ?? 'manuale'),
        ]);

        /* 6️⃣ RISPOSTA ------------------------------------------------------ */
        return response()->json([
            'success'      => true,
            'stock_level'  => $stockLevel->only([
                'id','quantity','supplier_lot_code','internal_lot_code'
            ]),
            'pivot_exists' => isset($order),
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
