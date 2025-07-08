<?php

namespace App\Http\Controllers;

use App\Models\LotNumber;
use App\Helpers\LotHelper;              // <-- usa il tuo helper esistente
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LotsController extends Controller
{
    /**
     * Prenota (e blocca) il prossimo codice lotto interno.
     *
     * Rotta:  POST /lots/reserve
     * Ritorna: { "next": "AA042" }
     */
    public function reserve(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $next = DB::transaction(function () use ($user) {

            /* lock row ***************/
            $last = LotNumber::lockForUpdate()
                ->orderByDesc('id')
                ->first();

            /* calcolo prossimo *******/

            // se non esiste ancora niente partiamo da AA000
            $code = $last
                ? LotHelper::next($last->code)   // <-- chiamata al tuo helper
                : 'AA000';

            /* inserimento ************/
            LotNumber::create([
                'code'        => $code,
                'status'      => 'reserved',
                'reserved_by' => $user->id,
            ]);

            return $code;
        });

        return response()->json(['next' => $next]);
    }
}
