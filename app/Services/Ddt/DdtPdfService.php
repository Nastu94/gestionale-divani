<?php

namespace App\Services\Ddt;

use App\Models\CustomerAddress;
use App\Models\Ddt;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Service PDF DDT (dompdf).
 *
 * Note chiave:
 * - Resolver destinatario/destinazione:
 *   - customers => customer_addresses (priorità shipping/billing/other)
 *   - occasional_customers => campi interni alla tabella
 * - Paginazione manuale (chunk righe) per avere:
 *   - footer pieno SOLO nell’ultima pagina
 *   - nelle altre pagine solo "SEGUE -->"
 */
class DdtPdfService
{
    /**
     * Streamma il PDF inline (aperto nel viewer del browser).
     */
    public function stream(Ddt $ddt): Response
    {
        /* Carica tutto ciò che serve al template */
        $ddt->loadMissing([
            'order.orderNumber',
            'order.customer.addresses',
            'order.occasionalCustomer',
            'rows.orderItem.product',
        ]);

        /* Recipient/Destination normalizzati */
        $parties = $this->resolveRecipientAndDestination($ddt->order);

        /* Paginazione: numero righe per pagina (taralo se cambia l'altezza righe) */
        $rowsPerPage = 10;
        $pages = $ddt->rows->values()->chunk($rowsPerPage);

        $data = [
            'ddt' => $ddt,

            /* Parti */
            'recipient' => $parties['recipient'],
            'destination' => $parties['destination'],
            'recipientCityLine' => $this->formatCityLine($parties['recipient']),
            'destinationCityLine' => $this->formatCityLine($parties['destination']),

            /* Pagine */
            'pages' => $pages,
        ];

        $pdf = Pdf::loadView('pdf.ddt', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $filename = sprintf('DDT_%d_%s.pdf', $ddt->number, $ddt->issued_at->format('Y-m-d'));

        return $pdf->stream($filename, ['Attachment' => 0]);
    }

    /**
     * Ritorna recipient/destination normalizzati.
     *
     * Convenzione pratica:
     * - Recipient: anagrafica cliente + indirizzo "billing" (se esiste) altrimenti fallback
     * - Destination: indirizzo "shipping" (se esiste) altrimenti fallback
     *
     * @return array{recipient: array<string,string>, destination: array<string,string>}
     */
    protected function resolveRecipientAndDestination(Order $order): array
    {
        /* Caso 1) Cliente standard */
        if (!empty($order->customer_id)) {
            $customer = $order->customer;

            /* Prendiamo gli indirizzi del cliente */
            $addresses = $customer?->addresses ?? collect();

            /* Destination: prima shipping */
            $shipping = $addresses->firstWhere('type', 'shipping')
                ?: $this->fallbackCustomerAddress($order->customer_id);

            /* Recipient: prima billing */
            $billing = $addresses->firstWhere('type', 'billing')
                ?: $shipping
                ?: $this->fallbackCustomerAddress($order->customer_id);

            return [
                'recipient' => $this->mapCustomerParty($customer, $billing),
                'destination' => $this->mapCustomerParty($customer, $shipping ?? $billing),
            ];
        }

        /* Caso 2) Cliente occasionale: dati già completi */
        $oc = $order->occasionalCustomer;

        $party = [
            'company'     => $oc?->company ?? '—',
            'address'     => $oc?->address ?? '',
            'postal_code' => $oc?->postal_code ?? '',
            'city'        => $oc?->city ?? '',
            'province'    => $oc?->province ?? '',
            'country'     => $oc?->country ?? 'Italia',
            'vat_number'  => $oc?->vat_number ?? '',
            'tax_code'    => $oc?->tax_code ?? '',
            'email'       => $oc?->email ?? '',
            'phone'       => $oc?->phone ?? '',
        ];

        /* Per ora recipient = destination (se in futuro gestisci destinazioni diverse, si estende qui) */
        return [
            'recipient' => $party,
            'destination' => $party,
        ];
    }

    /**
     * Fallback: prende un indirizzo qualsiasi con priorità shipping/billing/other.
     */
    protected function fallbackCustomerAddress(int $customerId): ?CustomerAddress
    {
        return CustomerAddress::query()
            ->where('customer_id', $customerId)
            ->orderByRaw("FIELD(type,'shipping','billing','other')")
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Mappa customer + customer_address in un array normalizzato.
     */
    protected function mapCustomerParty($customer, ?CustomerAddress $addr): array
    {
        return [
            'company'     => $customer?->company ?? '—',
            'address'     => $addr?->address ?? '',
            'postal_code' => $addr?->postal_code ?? '',
            'city'        => $addr?->city ?? '',
            'province'    => '', // customer_addresses non ha province nel tuo model attuale
            'country'     => $addr?->country ?? 'Italia',
            'vat_number'  => $customer?->vat_number ?? '',
            'tax_code'    => $customer?->tax_code ?? '',
            'email'       => $customer?->email ?? '',
            'phone'       => $customer?->phone ?? '',
        ];
    }

    /**
     * Helper: costruisce una riga unica stile "CAP CITTÀ (PR)".
     */
    protected function formatCityLine(array $p): string
    {
        $city = trim((string)($p['city'] ?? ''));
        $cap  = trim((string)($p['postal_code'] ?? ''));
        $pr   = trim((string)($p['province'] ?? ''));

        $line = trim($cap.' '.$city);
        if ($pr !== '') {
            $line .= " ({$pr})";
        }
        return trim($line);
    }
}
