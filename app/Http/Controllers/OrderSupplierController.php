<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNumber;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Services\ShortfallService;

class OrderSupplierController extends Controller
{
    /**
     * Display a listing of supplier purchase orders.
     *
     * @param  Request  $request
     * @return \Illuminate\Contracts\View\View
     */
    public function index(Request $request)
    {
        /*────────── PARAMETRI QUERY ──────────*/
        $sort    = $request->input('sort', 'ordered_at');      // campo sort default
        $dir     = $request->input('dir',  'desc') === 'asc' ? 'asc' : 'desc';
        $filters = $request->input('filter', []);              // array filtri

        /*────────── WHITELIST ORDINABILI ─────*/
        $allowedSorts = ['id', 'supplier', 'ordered_at', 'delivery_date', 'total'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'ordered_at';
        }

        /*────────── QUERY BASE ───────────────*/
        $orders = Order::query()
            ->with(['supplier:id,name', 'orderNumber:id,number,order_type'])  // eager-load
            ->whereHas('orderNumber', fn ($q) =>
                $q->where('order_type', 'supplier')
            )
            /*───── FILTRI COLUMNA ────────────*/
            ->when($filters['id']          ?? null,
                   fn ($q,$v) => $q->where('id', $v))
            ->when($filters['supplier']    ?? null,
                   fn ($q,$v) => $q->whereHas('supplier',
                                  fn ($q) => $q->where('name','like',"%$v%")))
            ->when($filters['ordered_at']  ?? null,
                   fn ($q,$v) => $q->whereDate('ordered_at',$v))
            ->when($filters['delivery_date'] ?? null,
                   fn ($q,$v) => $q->whereDate('delivery_date',$v))
            ->when($filters['total']       ?? null,
                   fn ($q,$v) => $q->where('total','like',"%$v%"))
            /*───── ORDINAMENTO ──────────────*/
            ->when($sort === 'supplier', function ($q) use ($dir) {
                $q->join('suppliers as s','orders.supplier_id','=','s.id')
                  ->orderBy('s.name',$dir)
                  ->select('orders.*');
            }, function ($q) use ($sort,$dir) {
                $q->orderBy($sort,$dir);
            })
            ->paginate(15)
            ->appends($request->query()); // preserva parametri per i link

        return view(
            'pages.orders.index-suppliers',
            compact('orders','sort','dir','filters')
        );
    }

    /**
     * Mostra le righe di un ordine fornitore.
     * 
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\JsonResponse
     */
    public function lines(Order $order): JsonResponse
    {
        abort_unless(
            $order->orderNumber->order_type === 'supplier',
            403, 'Non è un ordine fornitore'
        );

        /* ──────────── lookup registrazioni già caricate ─────────── */
        //  creiamo una mappa component_id → stock_level
        $received = $order->stockLevelLots        // eager-load della relazione
            ->keyBy('component_id');           // es.: [45] => StockLevel …

        /* ──────────── righe dell’ordine con extra info ─────────── */
        $rows = $order->items()
            ->with('component:id,code,description,unit_of_measure')
            ->orderBy('id')
            ->get()
            ->map(function ($it) use ($received) {

                $sl = $received->get($it->component_id);   // può essere null

                return [
                    'id'              => $it->id,  
                    'code'            => $it->component->code,
                    'desc'            => $it->component->description,
                    'unit'            => $it->component->unit_of_measure,
                    'qty'             => $it->quantity,
                    'price'           => (float) $it->unit_price,
                    'subtot'          => $it->quantity * $it->unit_price,

                    // ── campi aggiunti ────────────────────────────────
                    'qty_received'    => $sl ? $sl->quantity : 0,
                    'lot_supplier'    => $sl ? $sl->supplier_lot_code : null,
                    'internal_lot'    => $sl ? $sl->internal_lot_code : null,
                ];
            });

        return response()->json($rows);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Salva un nuovo ordine fornitore + righe.
     * Attende nel payload:
     *  - order_number_id  (FK al registro)
     *  - supplier_id
     *  - delivery_date
     *  - lines[ {component_id, quantity, price} … ]
     * 
     * @param  \Illuminate\Http\Request  $request
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_number_id'            => ['required','exists:order_numbers,id'],
            'supplier_id'                => ['required','exists:suppliers,id'],
            'delivery_date'              => ['required','date'],
            'lines'                      => ['required','array','min:1'],
            'lines.*.component_id'       => ['required','exists:components,id'],
            'lines.*.quantity'           => ['required','numeric','min:0.01'],
            'lines.*.last_cost'              => ['required','numeric','min:0'],
        ]);

        DB::transaction(function () use ($data) {

            /* ---------- Ordine ---------- */
            $order = Order::create([
                'order_number_id' => $data['order_number_id'],   // FK registro
                'supplier_id'     => $data['supplier_id'],
                'total'           => 0,                           // temp
                'ordered_at'      => now(),
                'delivery_date'   => $data['delivery_date'],
            ]);

            /* ---------- Righe & totale ---------- */
            $total = 0;

            foreach ($data['lines'] as $row) {
                $subtotal = $row['quantity'] * $row['last_cost'];

                OrderItem::create([
                    'order_id'     => $order->id,
                    'component_id' => $row['component_id'],
                    'quantity'     => $row['quantity'],
                    'unit_price'   => $row['last_cost'],
                ]);

                $total += $subtotal;
            }

            $order->update(['total' => $total]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Permette di visualizzare un ordine fornitore specifico.
     * 
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showApi(int $id): JsonResponse
    {
        try {
            $order = Order::with([
                        'supplier:id,name,vat_number,email,address',
                        'orderNumber:id,number,order_type',
                        'items.component:id,code,description,unit_of_measure',
                        'stockLevelLots.stockLevel:id,component_id'
                    ])
                    ->whereHas('orderNumber', fn ($q) => $q->where('order_type','supplier'))
                    ->findOrFail($id);

            // trasforma righe per il front-end
            $lines = $order->items->map(fn ($it) => [
                'component' => [
                    'id'          => $it->component->id,
                    'code'        => $it->component->code,
                    'description' => $it->component->description,
                    'unit_of_measure'        => $it->component->unit_of_measure,
                ],
                'qty'      => $it->quantity,
                'unit_of_measure' => $it->component->unit_of_measure,
                'last_cost' => $it->unit_price,
                'subtotal' => $it->quantity * $it->unit_price,
            ]);

            return response()->json([
                'id'              => $order->id,
                'order_number_id' => $order->order_number_id,
                'order_number'    => $order->orderNumber->number,
                'supplier'        => $order->supplier,
                'delivery_date'   => $order->delivery_date->format('Y-m-d'),
                'lines'           => $lines,
            ]);
        }
        catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Ordine non trovato'], 404);
        }
        catch (\Throwable $e) {
            report($e);                                 // log errori server
            return response()->json(['message' => 'Errore interno'], 500);
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(){
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Aggiorna un ordine fornitore e le sue righe.
     * 
     * @param  \Illuminate\Http\Request  $req
     * @param  \App\Models\Order         $order
     */
    public function update(Request $req, Order $order)
    {
        $data = $req->validate([
            'delivery_date'              => ['required','date'],
            'lines'                      => ['required','array','min:1'],
            'lines.*.id'                 => ['nullable','exists:order_items,id'],
            'lines.*.component_id'       => ['required','exists:components,id'],
            'lines.*.quantity'           => ['required','numeric','min:0.01'],
            'lines.*.last_cost'          => ['required','numeric','min:0'],
        ]);

        DB::transaction(function () use ($order, $data) {

            /* ── aggiornamento header ── */
            $order->update([
                'delivery_date' => $data['delivery_date'],
            ]);

            /* ── sincronizzazione righe ── */
            $keepIds = [];
            $total   = 0;

            foreach ($data['lines'] as $row) {

                $subtotal = $row['quantity'] * $row['last_cost'];
                $total   += $subtotal;

                // se l’id esiste aggiorno, altrimenti creo
                $item = $order->items()->updateOrCreate(
                    ['id' => $row['id'] ?? 0],      // condizione
                    [
                        'component_id' => $row['component_id'],
                        'quantity'     => $row['quantity'],
                        'unit_price'   => $row['last_cost'],
                    ]
                );
                $keepIds[] = $item->id;
            }

            // elimina righe rimosse dall’utente
            $order->items()->whereNotIn('id', $keepIds)->delete();

            // aggiorna il totale
            $order->update(['total' => $total]);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Salva bolla e, se occorre, genera l’eventuale ordine “short-fall”.
     *
     * @param  \Illuminate\Http\Request        $request
     * @param  \App\Models\Order               $order          Ordine che si sta chiudendo
     * @param  \App\Services\ShortfallService  $svc            Service che crea l’ordine mancante
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateRegistration(
        Request           $request,
        Order             $order,
        ShortfallService  $svc
    ): JsonResponse {

        /* ──────────────────────────────────────────────────────────────
        | 1‧ Validazione campi header ( data bolla + n° DDT )
        ────────────────────────────────────────────────────────────── */
        $data = $request->validate(
            [
                'delivery_date' => 'required|date',
                'bill_number'   => 'required|string|max:50',
            ],
            [
                'delivery_date.required' => 'La data di consegna è obbligatoria.',
                'bill_number.required'   => 'Il numero bolla è obbligatorio.',
            ]
        );

        /* ──────────────────────────────────────────────────────────────
        | 2‧ Aggiorna header dell’ordine
        ────────────────────────────────────────────────────────────── */
        $order->fill([
            'registration_date' => $data['delivery_date'],
            'bill_number'       => $data['bill_number'],
        ])->save();

        /* ──────────────────────────────────────────────────────────────
        | 3‧ Genera eventuale ordine “short-fall”
        |     (sarà null se tutte le quantità sono state evase)
        ────────────────────────────────────────────────────────────── */
        $followUp = $svc->capture($order);

        return response()->json([
            'success'            => true,
            'order'              => $order->only(['registration_date', 'bill_number']),
            'follow_up_order_id' => optional($followUp)->id,
            'follow_up_number'   => optional($followUp)->number,
        ]);
    }

    /**
     * Elimina un ordine fornitore e le sue righe.
     * – pulisce le righe in order_items
     * – rimuove la voce nel registro order_numbers
     * 
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Order $order): RedirectResponse
    {
        // sicurezza: deve essere davvero un ordine fornitore
        if ($order->orderNumber->order_type !== 'supplier') {
            abort(403, 'Operazione non consentita');
        }

        DB::transaction(function () use ($order) {

            // elimina righe (FK cascade OK, ma esplicito per chiarezza)
            $order->items()->delete();

            // elimina l’ordine
            $order->delete();

            // elimina la riga nel registro (opzionale; se vuoi conservarla, commenta)
            $order->orderNumber()->delete();
        });

        return back()->with('success', 'Ordine eliminato con successo.');
    }
}
