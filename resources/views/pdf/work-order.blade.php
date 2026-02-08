{{-- resources/views/pdf/work-order.blade.php --}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        /* ✅ Riservo spazio in basso per il footer fisso */
        @page { margin: 18pt 18pt 90pt 18pt; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #000; }

        /* Colore righe “tipo DDT” */
        .ink { color: #1f4eaa; }
        .b-ink { border: 1px solid #1f4eaa; }
        .bt-ink { border-top: 2px solid #1f4eaa; }
        .bb-ink { border-bottom: 2px solid #1f4eaa; }

        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }

        .head td { padding: 0; }

        .logo {
            width: 78pt; height: 78pt;
            border: 1px solid #999;
        }
        .company {
            padding-left: 12pt;
        }
        .company .name {
            font-size: 18pt;
            font-weight: 800;
            margin: 0 0 6pt 0;
        }
        .company .line { margin: 2pt 0; font-size: 9.5pt; }

        .docbox-wrap { width: 42%; }
        .doc-title {
            text-align: right;
            font-size: 14pt;
            font-weight: 700;
            margin-bottom: 6pt;
        }
        .doc-mini {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .doc-mini td { padding: 6pt 8pt; }
        .doc-mini .label { text-align: right; font-size: 12pt; }
        .doc-mini .box {
            width: 120pt;
            text-align: center;
            font-size: 12pt;
            font-weight: 800;
            border: 2px solid #1f4eaa;
        }
        .doc-sub {
            text-align: right;
            margin-top: 6pt;
            font-size: 10pt;
        }

        .section-title {
            font-weight: 800;
            margin: 10pt 0 4pt 0;
        }

        .box {
            border: 1px solid #1f4eaa;
            padding: 8pt;
            min-height: 44pt;
        }
        .box .big { font-size: 14pt; font-weight: 800; margin-bottom: 4pt; }
        .muted { font-size: 9pt; color: #333; }

        .items { margin-top: 12pt; }
        .items thead th {
            text-align: left;
            font-size: 11pt;
            font-weight: 800;
            padding: 6pt 6pt 8pt 6pt;
        }
        .items thead tr { border-bottom: 2px solid #1f4eaa; }
        .items tbody td {
            padding: 10pt 6pt;
            border-bottom: 1px solid #1f4eaa;
        }
        .right { text-align: right; }
        .desc-strong { font-weight: 800; }
        .refline {
            padding: 8pt 6pt;
            font-weight: 800;
        }

        /* ✅ Footer fisso (come DDT) */
        .footer-fixed{
            position: fixed;
            left: 18pt;
            right: 18pt;
            bottom: -72pt; /* 90pt (margin-bottom) - 18pt (margine reale dal bordo) */
        }

        .footer-grid {
            margin: 0;                 /* niente margin-top: è fisso */
            border: 1px solid #1f4eaa;
        }
        .footer-grid td {
            border-right: 1px solid #1f4eaa;
            padding: 10pt 8pt;
            height: 48pt;
        }
        .footer-grid td:last-child { border-right: 0; }
        .footer-grid .lbl { font-weight: 800; margin-bottom: 4pt; }

    </style>
</head>
<body>
@php
    $order = $wo->order;
    $orderNo = $order?->orderNumber?->number ?? $wo->order_id;

    $phaseLabels = [
        0 => 'Inserito',
        1 => 'Taglio',
        2 => 'Cucito',
        3 => 'Fusto',
        4 => 'Spugna',
        5 => 'Assemblaggio',
    ];
    $phaseName = $phaseLabels[$wo->phase] ?? (string)$wo->phase;

    $customerName = $order?->customer?->company
        ?? $order?->occasionalCustomer?->company
        ?? '—';

    // Destinazione: preferisci campi strutturati, fallback a shipping_address
    $street = '';
    $cityLine = '';

    if ($order?->occasional_customer_id && $order?->occasionalCustomer) {
        $oc = $order->occasionalCustomer;
        $street = (string)($oc->address ?? $order->shipping_address ?? '');
        $cap = (string)($oc->postal_code ?? '');
        $city = (string)($oc->city ?? '');
        $prov = (string)($oc->province ?? '');
        $cityLine = trim($cap.' '.$city);
        if ($prov !== '') $cityLine .= " ({$prov})";
    } elseif ($order?->customer?->shippingAddress) {
        $sa = $order->customer->shippingAddress;
        $street = (string)($sa->address ?? $order->shipping_address ?? '');
        $cap = (string)($sa->postal_code ?? '');
        $city = (string)($sa->city ?? '');
        $cityLine = trim($cap.' '.$city);
    } else {
        $raw = (string)($order?->shipping_address ?? '');
        $street = $raw;
        $cityLine = '';
    }

    $issuedDate = $wo->issued_at?->format('d/m/Y') ?? now()->format('d/m/Y');
    $issuedTime = $wo->issued_at?->format('H:i') ?? now()->format('H:i');

    $issuerName  = 'AL DIVANI S.R.L.';
    $issuerLine1 = 'SP 231 per Bitonto, Km 3 - 70032 Bitonto (BA)';
    $issuerLine2 = 'e-mail: info@aldivani.it     Pec: aldivani@legalmail.it';
    $issuerLine3 = 'C.F./P.Iva 08137940725';
@endphp

{{-- HEADER (stile DDT) --}}
<table class="head">
    <tr>
        <td style="width: 84pt;">
            <div class="logo"></div>
        </td>
        <td class="company">
            <div class="name">{{ $issuerName }}</div>
            <div class="bb-ink" style="height:0; margin: 0 0 6pt 0;"></div>
            <div class="line">{{ $issuerLine1 }}</div>
            <div class="line">{{ $issuerLine2 }}</div>
            <div class="line">{{ $issuerLine3 }}</div>
        </td>
        <td class="docbox-wrap">
            <div class="doc-title">Buono produzione</div>
            <table class="doc-mini">
                <tr>
                    <td class="label">nr.</td>
                    <td class="box">{{ $wo->number }}</td>
                </tr>
                <tr>
                    <td class="label">del</td>
                    <td class="box">{{ $issuedDate }}</td>
                </tr>
            </table>
            <div class="doc-sub">
                <strong>Ordine:</strong> {{ $orderNo }} &nbsp; | &nbsp;
                <strong>Fase:</strong> {{ $phaseName }} &nbsp; | &nbsp;
                <span class="muted">{{ $issuedTime }}</span>
            </div>
        </td>
    </tr>
</table>

{{-- DESTINATARIO / DESTINAZIONE --}}
<table style="margin-top: 14pt;">
    <tr>
        <td style="width: 48%; padding-right: 10pt;">
            <div class="section-title">Destinatario</div>
            <div class="box">
                <div class="big">{{ $customerName }}</div>

                {{-- ✅ Qui mancavano i dati: li mettiamo come nella destinazione --}}
                @if(trim($street) !== '')
                    <div>{{ $street }}</div>
                @endif
                @if(trim($cityLine) !== '')
                    <div>{{ $cityLine }}</div>
                @endif

                <div class="muted">Ordine: {{ $orderNo }}</div>
            </div>
        </td>
        <td style="width: 52%; padding-left: 10pt;">
            <div class="section-title">Destinazione</div>
            <div class="box">
                <div class="big">{{ $customerName }}</div>
                @if(trim($street) !== '')
                    <div>{{ $street }}</div>
                @endif
                @if(trim($cityLine) !== '')
                    <div>{{ $cityLine }}</div>
                @endif
            </div>
        </td>
    </tr>
</table>

{{-- RIGHE --}}
<table class="items">
    <thead>
        <tr>
            <th style="width: 18%;">Codice</th>
            <th>Descrizione</th>
            <th class="right" style="width: 14%;">Quantità</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="3" class="refline">
                Rif. Ordine {{ $orderNo }} — Buono {{ $wo->number }}/{{ $wo->year }} — Fase: {{ $phaseName }}
            </td>
        </tr>

        @foreach($wo->lines as $l)
            @php
                $variants = trim(implode(' ', array_filter([$l->fabric, $l->color])));
            @endphp
            <tr>
                <td>{{ $l->product_sku ?: '—' }}</td>
                <td>
                    <div class="desc-strong">{{ $l->product_name ?: '—' }}</div>
                    @if($variants !== '')
                        <div class="muted">{{ $variants }}</div>
                    @endif
                </td>
                <td class="right">
                    <strong>{{ rtrim(rtrim(number_format((float)$l->qty, 2, ',', '.'), '0'), ',') }}</strong>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- ✅ FOOTER fisso in fondo pagina (come DDT) --}}
<div class="footer-fixed">
    <table class="footer-grid" style="width: 100%;">
        <tr>
            <td style="width: 34%;">
                <div class="lbl">Operatore</div>
            </td>
            <td style="width: 33%;">
                <div class="lbl">Firma</div>
            </td>
            <td style="width: 33%;">
                <div class="lbl">Note</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
