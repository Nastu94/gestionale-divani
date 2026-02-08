<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Orders\OrderLabelPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class OrderCustomerLabelController extends Controller
{
    /**
     * Pagina wrapper con iframe PDF + auto-print.
     */
    public function print(Order $order): \Illuminate\View\View
    {
        // Permesso minimo: coerente con la UI "Visualizza"
        abort_unless(auth()->user()?->can('orders.customer.view'), 403);

        $pdfUrl = URL::temporarySignedRoute(
            'orders.customer.label.pdf',
            now()->addMinutes(5),
            ['order' => $order->id]
        );

        return view('orders.customer.label-print', [
            'order'  => $order,
            'pdfUrl' => $pdfUrl,
        ]);
    }

    /**
     * Stream PDF etichetta.
     */
    public function pdf(Order $order, OrderLabelPdfService $service): Response
    {
        abort_unless(auth()->user()?->can('orders.customer.view'), 403);

        return $service->stream($order);
    }
}
