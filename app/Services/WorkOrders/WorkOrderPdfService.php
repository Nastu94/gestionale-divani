<?php

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
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
            'lines' => fn ($q) => $q->orderBy('id'),
        ]);

        $pdf = Pdf::loadView('pdf.work-order', [
                'wo' => $workOrder,
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
