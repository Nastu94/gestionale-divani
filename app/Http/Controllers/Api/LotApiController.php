<?php

namespace App\Http\Controllers\Api;

use App\Models\StockLevel;
use App\Helpers\LotHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;


class LotApiController extends Controller
{
    /**
     * GET /lots/next
     */
    public function next()
    {
        $lastLot = StockLevel::query()->latest('internal_lot_code')->value('internal_lot_code');
        return response()->json([
            'next' => LotHelper::next($lastLot),
        ]);
    }
}
