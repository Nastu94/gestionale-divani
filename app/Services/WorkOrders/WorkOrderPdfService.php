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

        $resolvedMap = Component::withTrashed()
            ->whereIn('id', $resolvedIds)
            ->with(['category.phaseLinks'])
            ->get()
            ->keyBy('id');

        // NEW: Risoluzione stringente per i TESSU e blocco in caso di errore
        $resolver = app(\App\Services\TessuComponentResolver::class);
        $errors = [];

        foreach ($workOrder->lines as $line) {
            $item = $line->orderItem;
            if (!$item || !$item->product) continue;

            foreach ($item->product->components as $comp) {
                if ((int)($comp->pivot->is_variable ?? 0) === 1 && $comp->pivot->variable_slot === 'TESSU') {
                    $resolvedId = $item->variable?->resolved_component_id;
                    $fabricId = $item->variable?->fabric_id;
                    $colorId = $item->variable?->color_id;

                    try {
                        $eff = $resolver->resolveForStoredLine($item->product, $resolvedId, $fabricId, $colorId);
                        $resolvedMap->put('tessu_' . $item->id, $eff);
                    } catch (\Exception $e) {
                        $errors[] = "Prodotto {$item->product->sku}: " . $e->getMessage();
                    }
                }
            }
        }

        if (!empty($errors)) {
            abort(422, "Impossibile generare la stampa. Errori nella risoluzione TESSU:<br>" . implode("<br>", $errors));
        }

        $logoDataUri = null;

        /**
         * Percorsi possibili del logo.
         * - 1) public/img/logo.png
         * - 2) storage/app/public/img/logo.png (se lo tieni su storage pubblica)
         */
        $tryPaths = [
            public_path('images/aldivani-logo.png'),
            storage_path('app/public/images/aldivani-logo.png'),
        ];

        /**
         * Cerca il primo file esistente e costruisce una data-uri.
         */
        foreach ($tryPaths as $p) {
            $real = is_string($p) ? realpath($p) : false;

            // File trovato e leggibile
            if ($real && is_file($real) && is_readable($real)) {

                // Mime type minimale (aggiungi altri formati se ti servono)
                $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
                $mime = match ($ext) {
                    'png'         => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    default       => null,
                };

                if ($mime) {
                    $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($real));
                }

                break;
            }
        }

        $pdf = Pdf::loadView('pdf.work-order', [
                'wo' => $workOrder,
                'resolvedMap' => $resolvedMap,
                'logoDataUri' => $logoDataUri,
            ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'chroot' => [public_path(), storage_path('app/public'), base_path()],
            ]);

        $filename = sprintf('BUONO_%d-%d.pdf', $workOrder->year, $workOrder->number);

        return $pdf->stream($filename, ['Attachment' => 0]);
    }
}
