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

        .items tbody tr.comp td{
            padding: 4pt 6pt 8pt 6pt;
            font-size: 9pt;
            color: #333;
        }
        .items tbody tr.comp td:first-child{
            padding-left: 18pt; /* indent */
        }
        .items tbody tr.comp .comp-desc{
            font-style: italic;
        }
    </style>
</head>
<body>
@php

    /* Logo (quando lo avrai) */
    $logoPath = public_path('images/aldivani-logo.png');
    $hasLogo  = is_file($logoPath);
    
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
    $shippingZone = trim((string)($order?->shipping_zone ?? ''));
    $resolvedMap = $resolvedMap ?? collect();

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
    $blue = '#1f4eaa';
@endphp

{{-- HEADER (stile DDT) --}}
<table class="head">
    <tr>
        <td style="width: 120px;">
            {{-- Logo inline base64 (DomPDF safe) --}}
            @if(!empty($logoDataUri))
                <img src="{{ $logoDataUri }}" style="width: 105px; height: 105px;" alt="Logo">
            @else
                {{-- Fallback: placeholder se non trovato --}}
                <div class="logo" style="width:105px;height:105px;border:1px solid #ccc;display:flex;align-items:center;justify-content:center;">
                    <span style="font-size:10px;color:#666;">LOGO</span>
                </div>
            @endif
            <div style="font-size:9px;color:#999;">
    DEBUG logoDataUri: {{ empty($logoDataUri) ? 'NO' : 'YES' }}
</div>
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
                @if(trim($shippingZone) !== '')
                    <div class="muted"><strong>Zona:</strong> {{ $shippingZone }}</div>
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

                // NEW: note colore da variabili riga (order_product_variables.color_notes)
                $colorNotes = trim((string)($l->orderItem?->variable?->color_notes ?? ''));

                // NEW: componenti legati alla fase (fase 0 = Inserito => niente)
                $phase = (int) $wo->phase;
                $phaseComps = [];

                $orderItem = $l->orderItem;
                $productModel = $orderItem?->product;
                $resolvedId = $orderItem?->variable?->resolved_component_id;

                if ($phase > 0 && $productModel) {
                    foreach ($productModel->components as $comp) {

                        // Se componente BOM è variabile, sostituisci con quello risolto nell'ordine
                        $isVar = (bool) ($comp->pivot->is_variable ?? false);
                        $eff = ($isVar && $resolvedId && $resolvedMap->has($resolvedId))
                            ? $resolvedMap->get($resolvedId)
                            : $comp;

                        // Categoria -> fasi: usa component_category_phase (cast enum)
                        $links = $eff?->category?->phaseLinks ?? collect();

                        $matchesPhase = $links->contains(function ($ln) use ($phase) {
                            // $ln->phase è enum ProductionPhase (value int) oppure int
                            $p = $ln->phase;
                            if (is_object($p) && property_exists($p, 'value')) {
                                return (int) $p->value === (int) $phase;
                            }
                            return (int) $p === (int) $phase;
                        });

                        if (! $matchesPhase) continue;

                        // quantità componente = qty riga * qty BOM
                        $qtyComp = (float) $l->qty * (float) ($comp->pivot->quantity ?? 0);
                        if ($qtyComp <= 0) continue;

                        $phaseComps[] = [
                            'code' => $eff->code ?? '—',
                            'desc' => $eff->description ?? '—',
                            'qty'  => $qtyComp,
                            'unit' => $eff->unit_of_measure ?? 'pz',
                        ];
                    }
                }

                $hasPhaseComps = count($phaseComps) > 0;
                $prodTdStyle = $hasPhaseComps ? 'border-bottom:0;' : '';
            @endphp

            {{-- Riga PRODOTTO --}}
            <tr>
                <td style="{{ $prodTdStyle }}">{{ $l->product_sku ?: '—' }}</td>
                <td style="{{ $prodTdStyle }}">
                    <div class="desc-strong">{{ $l->product_name ?: '—' }}</div>

                    @if($variants !== '')
                        <div class="muted">{{ $variants }}</div>
                    @endif

                    {{-- NEW: note colore --}}
                    @if($colorNotes !== '')
                        <div class="muted"><strong>Note colore:</strong> {{ $colorNotes }}</div>
                    @endif
                </td>
                <td class="right" style="{{ $prodTdStyle }}">
                    <strong>{{ rtrim(rtrim(number_format((float)$l->qty, 2, ',', '.'), '0'), ',') }}</strong>
                </td>
            </tr>

            {{-- NEW: Riga/e COMPONENTE/i legati alla fase --}}
            @if($hasPhaseComps)
                @foreach($phaseComps as $idx => $c)
                    @php
                        // Evita righe di separazione multiple: bordino solo sull'ultima riga componente
                        $isLast = ($idx === count($phaseComps) - 1);
                        $compTdStyle = $isLast ? '' : 'border-bottom:0;';
                    @endphp
                    <tr class="comp">
                        <td style="{{ $compTdStyle }}">{{ $c['code'] }}</td>
                        <td class="comp-desc" style="{{ $compTdStyle }}">
                            ↳ {{ $c['desc'] }}
                        </td>
                        <td class="right" style="{{ $compTdStyle }}">
                            {{ rtrim(rtrim(number_format((float)$c['qty'], 2, ',', '.'), '0'), ',') }}
                            <span class="muted">{{ strtoupper($c['unit']) }}</span>
                        </td>
                    </tr>
                @endforeach
            @endif
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
