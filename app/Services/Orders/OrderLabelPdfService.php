<?php

namespace App\Services\Orders;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class OrderLabelPdfService
{
    // Usa interi e poi fai match nel CSS (567pt x 312pt)
    private const PAPER = [0, 0, 567, 312];

    public function stream(Order $order): Response
    {
        $order->loadMissing([
            'orderNumber:id,number',
            'customer:id,company',
            'customer.shippingAddress:id,customer_id,address,city,postal_code,country',
            'occasionalCustomer:id,company,address,postal_code,city,province,country',
            'items.product:id,name,sku',
            'items.variable.fabric:id,name,code',
            'items.variable.color:id,name,code',
        ]);

        $isOccasional = !is_null($order->occasional_customer_id);
        $brand        = $isOccasional ? 'KOMODO' : 'AL Divani';

        [$name, $street, $cityLine] = $this->resolveRecipientLines($order);

        $orderNo = optional($order->orderNumber)->number ?? (string) $order->id;

        $labels = $order->items->map(function ($it) {
            $qty     = (float) $it->quantity;
            $prefix  = ($qty > 1.0) ? ((int)$qty . ' ') : '';
            $prod    = strtoupper((string) optional($it->product)->name);

            $fabric = optional(optional($it->variable)->fabric)->name
                ?? optional(optional($it->variable)->fabric)->code;

            $color  = optional(optional($it->variable)->color)->name
                ?? optional(optional($it->variable)->color)->code;

            $varLine = trim(implode(' ', array_filter([(string)$fabric, (string)$color])));

            return [
                'main' => trim($prefix . $prod),
                'var'  => $varLine !== '' ? strtoupper($varLine) : null,
            ];
        })->values();

        // ✅ Come proforma (se vuoi fisso). Se invece lo vuoi dinamico col city del cliente, tieni la tua logica.
        $brandCity = $isOccasional ? 'KOMODO' : 'AL DIVANI';

        $shippingZone = trim((string) ($order->shipping_zone ?? ''));
        $shippingZone = $shippingZone !== '' ? $shippingZone : null;

        $pdf = Pdf::loadView('pdf.order-label', [
                'brand'     => strtoupper($brand),
                'name'      => strtoupper($name),
                'street'    => strtoupper($street),
                'cityLine'  => strtoupper($cityLine),
                'shippingZone' => $shippingZone ? strtoupper($shippingZone) : null,
                'brandCity' => strtoupper($brandCity),
                'orderNo'   => strtoupper($orderNo),
                'labels'    => $labels,
            ])
            ->setPaper(self::PAPER)
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = sprintf('ETICHETTA_%s.pdf', $orderNo);

        return $pdf->stream($filename, ['Attachment' => 0]);
    }

    /**
     * @return array{0:string,1:string,2:string} [name, street, cityLine]
     */
    private function resolveRecipientLines(Order $order): array
    {
        // OCCASIONAL
        if (!is_null($order->occasional_customer_id) && $order->occasionalCustomer) {
            $oc = $order->occasionalCustomer;

            $name   = $oc->company ?? '—';
            $street = $oc->address ?? ($order->shipping_address ?? '');
            $cap    = $oc->postal_code ?? '';
            $city   = $oc->city ?? '';
            $prov   = $oc->province ?? '';

            $cityLine = trim($cap . ' ' . $city);
            if ($prov !== '') $cityLine .= " ({$prov})";

            return [$name, $street, $cityLine];
        }

        // CUSTOMER (standard)
        $cust = $order->customer;
        $addr = $cust?->shippingAddress;

        $name = $cust?->company ?? '—';

        if ($addr) {
            $street  = $addr->address ?? '';
            $cap     = $addr->postal_code ?? '';
            $city    = $addr->city ?? '';
            $cityLine = trim($cap . ' ' . $city);

            return [$name, $street, $cityLine];
        }

        // Fallback shipping_address “best effort”
        $raw = (string) ($order->shipping_address ?? '');
        $parts = array_map('trim', explode(',', $raw));
        $street  = $parts[0] ?? $raw;
        $cityLine = trim(implode(' ', array_slice($parts, 1)));

        return [$name, $street, $cityLine];
    }
}
