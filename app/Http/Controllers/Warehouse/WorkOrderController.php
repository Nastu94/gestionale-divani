<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function print(WorkOrder $workOrder): View
    {
        abort_unless(auth()->user()?->can('stock.exit'), 403);

        $pdfUrl = URL::temporarySignedRoute(
            'warehouse.work_orders.pdf',
            now()->addMinutes(5),
            ['workOrder' => $workOrder->id]
        );

        return view('warehouse.work-orders.print', [
            'pdfUrl' => $pdfUrl,
        ]);
    }

    public function pdf(WorkOrder $workOrder, WorkOrderPdfService $pdfService): Response
    {
        abort_unless(auth()->user()?->can('stock.exit'), 403);

        return $pdfService->stream($workOrder);
    }
}
