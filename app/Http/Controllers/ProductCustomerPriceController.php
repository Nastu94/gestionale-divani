<?php
// app/Http/Controllers/ProductCustomerPriceController.php

namespace App\Http\Controllers;

use App\Models\CustomerProductPrice;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gestione prezzi cliente-prodotto con versioni non sovrapposte.
 * - index(): lista per modale "Listino".
 * - resolve(): escamotage per capire se esiste una versione valida alla data (default oggi).
 * - store(): crea nuova versione (opzionale chiusura automatica della precedente).
 * - update(): correzione retroattiva (no overlap).
 * - destroy(): elimina la versione selezionata.
 */
class ProductCustomerPriceController extends Controller
{
    /**
     * Ritorna la lista prezzi per un prodotto (solo consultazione).
     */
    public function index(Request $request, Product $product): JsonResponse
    {
        $today = Carbon::today();

        $query = CustomerProductPrice::with('customer:id,company') 
            ->where('product_id', $product->id)
            ->orderByRaw('COALESCE(valid_from, DATE("1970-01-01")) DESC');

        // Paginazione opzionale (se vuoi paginare nel modale)
        $prices = $query->get()->map(function (CustomerProductPrice $row) use ($today) {
            $isCurrent = ($row->valid_from === null || $row->valid_from->lte($today))
                      && ($row->valid_to === null   || $row->valid_to->gte($today));

            return [
                'id'         => $row->id,
                'customer'   => [
                    'id'      => $row->customer_id,
                    'company' => optional($row->customer)->company,
                ],
                'price'      => (string) $row->price,
                'currency'   => $row->currency,
                'valid_from' => optional($row->valid_from)->toDateString(),
                'valid_to'   => optional($row->valid_to)->toDateString(),
                'notes'      => $row->notes,
                'status'     => $isCurrent ? 'corrente' : ($row->valid_from && $row->valid_from->gt($today) ? 'futuro' : 'passato'),
            ];
        });

        return response()->json([
            'data' => $prices,
            'meta' => ['count' => $prices->count()],
        ]);
    }

    /**
     * Escamotage: risolve (product, customer, date) => versione "valida" o ultimo storico.
     * GET /products/{product}/prices/resolve?customer_id=&date=YYYY-MM-DD
     */
    public function resolve(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'date'        => ['nullable', 'date'],
        ]);

        $customerId = (int) $validated['customer_id'];
        $date       = isset($validated['date']) ? Carbon::parse($validated['date'])->startOfDay() : Carbon::today();

        // 1) Versione valida alla data
        $current = CustomerProductPrice::where('product_id', $product->id)
            ->where('customer_id', $customerId)
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->orderByRaw('COALESCE(valid_from, DATE("1970-01-01")) DESC')
            ->first();

        // 2) Ultimo storico (se non c'è un current)
        $latestArchived = null;
        if (!$current) {
            $latestArchived = CustomerProductPrice::where('product_id', $product->id)
                ->where('customer_id', $customerId)
                ->whereNotNull('valid_to')
                ->orderBy('valid_to', 'DESC')
                ->first();
        }

        return response()->json([
            'current'        => $current,
            'latestArchived' => $latestArchived,
        ]);
    }

    /**
     * Crea nuova versione.
     * Supporta "chiudi automaticamente la versione che copre valid_from" se richiesto.
     */
    public function store(Request $request, Product $product): JsonResponse
    {
        // Normalizza decimali con virgola (es. "12,50" => "12.50")
        $priceInput = str_replace(',', '.', (string) $request->input('price'));

        $data = $request->validate([
            'customer_id'     => ['required', 'exists:customers,id'],
            'price'           => ['required', 'numeric', 'min:0'],
            'currency'        => ['nullable', 'size:3'],
            'valid_from'      => ['nullable', 'date'],
            'valid_to'        => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
            'auto_close_prev' => ['sometimes', 'boolean'], // se true, chiude la precedente alla (valid_from - 1)
        ]);
        $data['price']    = $priceInput;
        $data['currency'] = $data['currency'] ?? 'EUR';

        // Verifica coerenza range
        $from = $data['valid_from'] ? Carbon::parse($data['valid_from'])->startOfDay() : null;
        $to   = $data['valid_to']   ? Carbon::parse($data['valid_to'])->startOfDay()   : null;

        if ($from && $to && $to->lt($from)) {
            return response()->json([
                'message' => 'La data di fine validità non può precedere la data di inizio.',
                'errors'  => ['valid_to' => ['valid_to < valid_from']],
            ], 422);
        }

        // Anti-overlap (+ gestione chiusura automatica)
        return DB::transaction(function () use ($product, $data, $from, $to) {
            $overlaps = CustomerProductPrice::where('product_id', $product->id)
                ->where('customer_id', $data['customer_id'])
                ->when(true, function ($q) use ($from, $to) {
                    // Normalizziamo l'intervallo new: [from, to] con null = infinito
                    // Overlap se NON (existing.to < new.from OR existing.from > new.to)
                    $q->where(function ($qq) use ($from, $to) {
                        $qq->where(function ($qqq) use ($from) {
                            if ($from) {
                                $qqq->where(function ($z) use ($from) {
                                    $z->whereNull('valid_to')->orWhere('valid_to', '>=', $from);
                                });
                            } else {
                                // new.from = -∞ ⇒ qualsiasi existing.to non è < -∞
                                $qqq->whereRaw('1=1');
                            }
                        })->where(function ($qqq) use ($to) {
                            if ($to) {
                                $qqq->where(function ($z) use ($to) {
                                    $z->whereNull('valid_from')->orWhere('valid_from', '<=', $to);
                                });
                            } else {
                                // new.to = +∞ ⇒ qualsiasi existing.from non è > +∞
                                $qqq->whereRaw('1=1');
                            }
                        });
                    });
                })
                ->get();

            if ($overlaps->isNotEmpty()) {
                // Caso speciale: auto-chiudi la singola versione che copre 'from'
                $wantsAutoClose = (bool) ($data['auto_close_prev'] ?? false);
                $canAutoClose   = $wantsAutoClose && $from !== null && $to === null;

                if ($canAutoClose) {
                    // Cerco una sola riga "aperta o estesa" che copre 'from'
                    $covering = $overlaps->first(function (CustomerProductPrice $row) use ($from) {
                        $coversFrom = (is_null($row->valid_from) || $row->valid_from->lte($from))
                                   && (is_null($row->valid_to)   || $row->valid_to->gte($from));
                        return $coversFrom;
                    });

                    if ($covering) {
                        // Chiudo la riga precedente al giorno prima del nuovo from
                        $newTo = $from->copy()->subDay(); // valid_to = from - 1 giorno
                        if ($covering->valid_to === null || $covering->valid_to->gt($newTo)) {
                            $covering->update(['valid_to' => $newTo->toDateString()]);
                        }

                        // Ricalcolo overlap dopo la chiusura
                        $overlaps = CustomerProductPrice::where('product_id', $product->id)
                            ->where('customer_id', $data['customer_id'])
                            ->where(function ($qq) use ($from) {
                                $qq->where(function ($z) use ($from) {
                                    $z->whereNull('valid_to')->orWhere('valid_to', '>=', $from);
                                });
                            })
                            ->get();
                    }
                }
            }

            // Dopo eventuale chiusura, se ci sono ancora overlap ⇒ blocco
            if ($overlaps->isNotEmpty()) {
                return response()->json([
                    'message' => 'Esiste già un prezzo valido in parte del periodo selezionato per questo cliente.',
                    'errors'  => ['valid_from' => ['overlap']],
                ], 422);
            }

            $created = CustomerProductPrice::create([
                'product_id' => $product->id,
                'customer_id'=> $data['customer_id'],
                'price'      => $data['price'],
                'currency'   => $data['currency'],
                'valid_from' => $from ? $from->toDateString() : null,
                'valid_to'   => $to   ? $to->toDateString()   : null,
                'notes'      => $data['notes'] ?? null,
            ]);

            return response()->json(['data' => $created], 201);
        });
    }

    /**
     * Aggiorna (correzione retroattiva) una versione esistente.
     * Non crea nuove righe: valida anti-overlap e salva.
     */
    public function update(Request $request, Product $product, CustomerProductPrice $price): JsonResponse
    {

        // Evita update cross-prodotto
        if ($price->product_id !== $product->id) {
            abort(404);
        }

        $priceInput = str_replace(',', '.', (string) $request->input('price'));

        $data = $request->validate([
            'customer_id'   => ['required', 'exists:customers,id'],
            'price'         => ['required', 'numeric', 'min:0'],
            'currency'      => ['nullable', 'size:3'],
            'valid_from'    => ['nullable', 'date'],
            'valid_to'      => ['nullable', 'date'],
            'notes'         => ['nullable', 'string'],
            'updated_at'    => ['nullable', 'date'], // concorrenza ottimistica (facoltativa)
        ]);
        $data['price']    = $priceInput;
        $data['currency'] = $data['currency'] ?? 'EUR';

        // Concorrenza ottimistica: se inviato updated_at, verifica staleness
        if ($request->filled('updated_at') && $price->updated_at && $price->updated_at->toISOString() !== Carbon::parse($request->input('updated_at'))->toISOString()) {
            return response()->json([
                'message' => 'Il record è stato modificato da un altro utente. Ricarica i dati.',
            ], 409);
        }

        $from = $data['valid_from'] ? Carbon::parse($data['valid_from'])->startOfDay() : null;
        $to   = $data['valid_to']   ? Carbon::parse($data['valid_to'])->startOfDay()   : null;

        if ($from && $to && $to->lt($from)) {
            return response()->json([
                'message' => 'La data di fine validità non può precedere la data di inizio.',
                'errors'  => ['valid_to' => ['valid_to < valid_from']],
            ], 422);
        }

        // Anti-overlap ignorando se stessa
        $overlaps = CustomerProductPrice::where('product_id', $product->id)
            ->where('customer_id', $data['customer_id'])
            ->where('id', '!=', $price->id)
            ->where(function ($qq) use ($from, $to) {
                $qq->where(function ($z) use ($from) {
                    if ($from) {
                        $z->whereNull('valid_to')->orWhere('valid_to', '>=', $from);
                    } else {
                        $z->whereRaw('1=1');
                    }
                })->where(function ($z) use ($to) {
                    if ($to) {
                        $z->whereNull('valid_from')->orWhere('valid_from', '<=', $to);
                    } else {
                        $z->whereRaw('1=1');
                    }
                });
            })
            ->exists();

        if ($overlaps) {
            return response()->json([
                'message' => 'L’intervallo selezionato si sovrappone ad altre versioni per questo cliente.',
                'errors'  => ['valid_from' => ['overlap']],
            ], 422);
        }

        $price->update([
            'customer_id' => $data['customer_id'],
            'price'       => $data['price'],
            'currency'    => $data['currency'],
            'valid_from'  => $from ? $from->toDateString() : null,
            'valid_to'    => $to   ? $to->toDateString()   : null,
            'notes'       => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $price->fresh('customer')]);
    }

    /**
     * Elimina una versione (attiva, futura o storica).
     */
    public function destroy(Request $request, Product $product, CustomerProductPrice $price): JsonResponse
    {

        if ($price->product_id !== $product->id) {
            abort(404);
        }

        $price->delete();

        return response()->json(['message' => 'Versione eliminata.']);
    }
}
