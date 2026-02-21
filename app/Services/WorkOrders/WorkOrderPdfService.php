<?php

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
use App\Models\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class WorkOrderPdfService
{
    public function stream(WorkOrder $workOrder): Response
    {
        $workOrder->loadMissing([
            'order.orderNumber:id,number',
            'order.customer:id,company',
            'order.customer.shippingAddress:id,customer_id,address,city,postal_code,country',
            'order.occasionalCustomer:id,company,address,postal_code,city,province,country',

            // NEW: per note colori e componenti di fase
            'lines.orderItem:id,order_id,product_id,quantity',
            'lines.orderItem.variable:id,order_item_id,fabric_id,color_id,color_notes,resolved_component_id',
            'lines.orderItem.product:id,sku,name',
            'lines.orderItem.product.components.category.phaseLinks',

            'lines' => fn ($q) => $q->orderBy('id'),
        ]);

        /*──────────────── Mappa componenti risolti (slot variabile BOM) ────────────────*/
        $resolvedIds = $workOrder->lines
            ->pluck('orderItem.variable.resolved_component_id')
            ->filter()
            ->unique()
            ->values();

        $resolvedMap = Component::query()
            ->whereIn('id', $resolvedIds)
            ->with(['category.phaseLinks'])
            ->get()
            ->keyBy('id');

        $pdf = Pdf::loadView('pdf.work-order', [
                'wo' => $workOrder,
                'resolvedMap' => $resolvedMap,
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = sprintf('BUONO_%d-%d.pdf', $workOrder->year, $workOrder->number);

        return $pdf->stream($filename, ['Attachment' => 0]);
    }
}
