<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OccasionalCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Gestisce la creazione di clienti “occasionali” direttamente
 * dal modale Ordini Cliente. Nessuna Form Request: la validazione
 * viene eseguita inline nel metodo store().
 */
class OccasionalCustomerController extends Controller
{
    /**
     * Crea un nuovo cliente occasionale.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        /*────────────────── VALIDAZIONE ──────────────────*/
        $data = $request->validate([
            'company'      => ['required', 'string', 'max:191'],
            'vat_number'   => ['nullable', 'string', 'max:20'],
            'tax_code'     => ['nullable', 'string', 'max:20'],
            'address'      => ['required', 'string', 'max:191'],
            'postal_code'  => ['required', 'string', 'max:20'],
            'city'         => ['required', 'string', 'max:100'],
            'province'     => ['required', 'string', 'max:10'],
            'country'      => ['required', 'string', 'size:2', 'alpha'],
            'email'        => ['required', 'email', 'max:191'],
            'phone'        => ['required', 'string', 'max:30'],
            'note'         => ['nullable', 'string'],
        ]);

        /*────────────────── DEDUPLICA (opz.) ──────────────*/
        // Se esiste già un guest con stessa ragione sociale, lo riusa.
        $guest = OccasionalCustomer::firstOrCreate(
            ['company' => $data['company']],
            $data
        );

        /*────────────────── RESPONSE ──────────────────────*/
        return response()->json($guest, $guest->wasRecentlyCreated ? 201 : 200);
    }
}
