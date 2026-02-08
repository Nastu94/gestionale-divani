{{-- resources/views/pdf/ddt.blade.php --}}
@php
    /* Dati azienda (poi li metti in config) */
    $companyName = 'AL DIVANI S.R.L.';
    $companyAddr = 'SP 231 per Bitonto, Km 3 - 70032 Bitonto (BA)';
    $companyMail = 'info@aldivani.it';
    $companyPec  = 'aldivani@legalmail.it';
    $companyVat  = '08137940725';
    $companyIban = 'IT61B0306941545100000008782';

    $order = $ddt->order;

    $orderNo   = $order->orderNumber?->number ?? $order->id;
    $orderDate = $order->ordered_at ? \Carbon\Carbon::parse($order->ordered_at)->format('d/m/Y') : '';
    $ddtDate   = $ddt->issued_at->format('d/m/Y');

    /* Totale documento = totale DDT (non per pagina) */
    $total = $ddt->rows->sum(fn($r) => ((float)$r->quantity * (float)$r->unit_price));

    /* Colore proforma */
    $blue = '#1e3a8a';

    /* Logo (quando lo avrai) */
    $logoPath = public_path('images/aldivani-logo.png');
    $hasLogo  = is_file($logoPath);

    /* Pagine (chunk fatto nel service) */
    $pages = $pages ?? collect([$ddt->rows]);
@endphp

<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.15;
            margin: 0;
        }

        /* Singola pagina A4 dentro i margini: 297mm - 24mm = 273mm */
        .page {
            position: relative;
            height: 273mm;
        }

        /* Contenuto: riserva spazio al footer dell'ultima pagina */
        .content {
            padding-bottom: 70mm; /* spazio “di sicurezza” per evitare overlap col footer */
        }

        /* Footer fisso in basso nella singola pagina */
        .page-footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            page-break-inside: avoid;
        }

        .page-break { page-break-after: always; }

        .blue-line { border-top: 2px solid {{ $blue }}; margin: 6px 0 10px; }

        .h1 { font-size: 22px; font-weight: 700; }
        .muted { color: #333; }

        .box { border: 1px solid {{ $blue }}; padding: 8px; }
        .box-title { font-weight: 700; margin-bottom: 4px; }
        .box-line { white-space: nowrap; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: 700; padding: 6px 6px; }
        td { padding: 6px 6px; vertical-align: top; }

        .tbl-head { border-bottom: 2px solid {{ $blue }}; }
        .right { text-align: right; }
        .center { text-align: center; }

        .tot-wrap { border-top: 2px solid {{ $blue }}; margin-top: 10px; }
        .tot-box { border-left: 1px solid {{ $blue }}; padding: 10px; font-size: 18px; font-weight: 700; }

        .footer-grid { width: 100%; border: 1px solid {{ $blue }}; margin-top: 10px; }
        .footer-grid td { border: 1px solid {{ $blue }}; font-size: 11px; padding: 8px; }

        .legal { margin-top: 8px; font-size: 9px; text-align: center; color: #222; }
        .page-num { font-size: 10px; margin-top: 6px; }

        .segue {
            text-align: right;
            font-size: 12px;
            font-weight: 700;
        }

        .order-ref {
            margin-top: 8px;
            font-size: 10px;
            font-weight: 700;
        }

        .order-note {
            margin-top: 4px;
            font-size: 10px;
        }
    </style>
</head>
<body>

@foreach($pages as $pageRows)
    @php $isLast = $loop->last; @endphp

    <div class="page">
        <div class="content">

            {{-- HEADER --}}
            <table>
                <tr>
                    <td style="width: 120px;">
                        @if($hasLogo)
                            <img src="{{ $logoPath }}" style="width: 105px; height: 105px;">
                        @else
                            {{-- Placeholder logo (finché non lo hai) --}}
                            <div style="width:105px;height:105px;border:1px solid {{ $blue }};display:flex;align-items:center;justify-content:center;">
                                LOGO
                            </div>
                        @endif
                    </td>

                    <td>
                        <div class="h1">{{ $companyName }}</div>
                        <div class="blue-line"></div>
                        <div class="muted">{{ $companyAddr }}</div>
                        <div class="muted">e-mail: {{ $companyMail }} &nbsp;&nbsp; Pec: {{ $companyPec }}</div>
                        <div class="muted">C.F./P.Iva {{ $companyVat }}</div>
                        <div class="muted">BANCA INTESA IBAN: {{ $companyIban }}</div>
                    </td>

                    <td style="width: 260px; vertical-align: top;">
                        <div style="margin-top: 46px; text-align: right;">
                            <span style="font-size: 16px;">Doc. di trasporto nr.</span>
                            <span class="box" style="display:inline-block;width:60px;text-align:center;font-weight:700;">{{ $ddt->number }}</span>
                            <span style="font-size: 16px; margin-top: 6px;">&nbsp; del&nbsp;</span>
                            <span class="box" style="display:inline-block;width:90px;text-align:center;font-weight:700;margin-top: 6px;">{{ $ddtDate }}</span>
                        </div>
                    </td>
                </tr>
            </table>

            {{-- DESTINATARIO / DESTINAZIONE --}}
            <table style="margin-top: 10px;">
                <tr>
                    <td style="width: 50%; padding-right: 10px;">
                        <div class="box-title">Destinatario</div>
                        <div class="box">
                            <div style="font-weight:700;">{{ $recipient['company'] }}</div>

                            @if(!empty($recipient['address']))
                                <div class="box-line">{{ $recipient['address'] }}</div>
                            @endif

                            @if(!empty($recipientCityLine))
                                <div class="box-line">{{ $recipientCityLine }}</div>
                            @endif

                            <div class="box-line">
                                @if(!empty($recipient['tax_code'])) C.F. {{ $recipient['tax_code'] }} @endif
                                @if(!empty($recipient['vat_number']))&nbsp;&nbsp; P.Iva {{ $recipient['vat_number'] }} @endif
                            </div>
                        </div>
                    </td>

                    <td style="width: 50%; padding-left: 10px;">
                        <div class="box-title">Destinazione</div>
                        <div class="box">
                            <div style="font-weight:700;">{{ $destination['company'] }}</div>

                            @if(!empty($destination['address']))
                                <div class="box-line">{{ $destination['address'] }}</div>
                            @endif

                            @if(!empty($destinationCityLine))
                                <div class="box-line">{{ $destinationCityLine }}</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            {{-- TABELLA ARTICOLI (solo righe di questa pagina) --}}
            <table style="margin-top: 14px;">
                <thead class="tbl-head">
                    <tr>
                        <th style="width: 10%;">Codice</th>
                        <th style="width: 52%;">Descrizione</th>
                        <th style="width: 8%;"  class="center">Quantità</th>
                        <th style="width: 12%;" class="right">Prezzo ivato</th>
                        <th style="width: 8%;"  class="center">Sconto</th>
                        <th style="width: 12%;" class="right">Importo</th>
                        <th style="width: 6%;"  class="center">Iva</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pageRows as $r)
                        @php
                            $it   = $r->orderItem;
                            $prod = $it?->product;

                            $sku  = $prod?->sku ?? '';
                            $desc = $prod?->name ?? '—';

                            $lineTotal = (float)$r->quantity * (float)$r->unit_price;
                        @endphp

                        <tr>
                            <td>{{ $sku }}</td>
                            <td style="text-transform: uppercase;">
                                {{ $desc }}
                            </td>
                            <td class="center">{{ number_format((float)$r->quantity, 0, ',', '.') }}</td>
                            <td class="right">€ {{ number_format((float)$r->unit_price, 2, ',', '.') }}</td>
                            <td class="center"></td>
                            <td class="right">€ {{ number_format($lineTotal, 2, ',', '.') }}</td>
                            <td class="center">{{ $r->vat }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- RIFERIMENTO ORDINE: SOLO ALLA FINE, DOPO L'ULTIMA RIGA (quindi nell'ultima pagina) --}}
            @if($isLast)
                <div class="order-ref">
                    Rif. Conferma d'ordine {{ $orderNo }} @if($orderDate) del {{ $orderDate }} @endif
                </div>

                {{-- Nota interna (orders.note) solo se presente --}}
                @if(!empty($order->note))
                    <div class="order-note">{{ $order->note }}</div>
                @endif
            @endif

        </div> {{-- /content --}}

        {{-- FOOTER PER PAGINA --}}
        <div class="page-footer">
            @if(!$isLast)
                {{-- Pagine intermedie: SOLO "SEGUE -->" --}}
                <div class="segue">SEGUE --&gt;</div>
            @else
                {{-- Ultima pagina: footer completo --}}
                <table class="tot-wrap">
                    <tr>
                        <td style="width: 70%;"></td>
                        <td class="tot-box" style="width: 30%;">
                            <div style="display:flex; justify-content: space-between;">
                                <span>Tot. documento</span>
                                <span>€ {{ number_format($total, 2, ',', '.') }}</span>
                            </div>
                        </td>
                    </tr>
                </table>

                <table class="footer-grid">
                    <tr>
                        <td style="width: 45%;">
                            <div style="font-weight: 700;">Incaricato del trasporto</div>
                            <div>{{ $ddt->carrier_name ?? '' }}</div>
                        </td>
                        <td style="width: 25%;">
                            <div style="font-weight: 700;">Causale del trasporto</div>
                            <div style="font-weight: 700;">{{ $ddt->transport_reason ?? '' }}</div>
                        </td>
                        <td style="width: 30%;">
                            <div style="font-weight: 700;">Firma incaricato del trasporto</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="width: 10%;">
                            <div style="font-weight: 700;">Nr. colli</div>
                            <div>{{ $ddt->packages ?? '' }}</div>
                        </td>
                        <td style="width: 10%;">
                            <div style="font-weight: 700;">Peso</div>
                            <div>{{ $ddt->weight ?? '' }}</div>
                        </td>
                        <td style="width: 20%;">
                            <div style="font-weight: 700;">Aspetto esteriore dei beni</div>
                            <div>{{ $ddt->goods_appearance ?? '' }}</div>
                        </td>
                        <td style="width: 20%;">
                            <div style="font-weight: 700;">Porto</div>
                            <div>{{ $ddt->port ?? '' }}</div>
                        </td>
                        <td style="width: 20%;">
                            <div style="font-weight: 700;">Data e ora inizio trasporto</div>
                            <div>{{ $ddt->transport_started_at ? $ddt->transport_started_at->format('d/m/Y H:i') : '' }}</div>
                        </td>
                        <td style="width: 20%;">
                            <div style="font-weight: 700;">Firma destinatario</div>
                        </td>
                    </tr>
                </table>

                <div class="page-num">Pag. {{ $loop->iteration }} / {{ $loop->count }}</div>

                <div class="legal">
                    Nel rispetto dalla normativa vigente, ivi incluso DL 196/03 e reg. UE 2016/679, informiamo che i Vs. dati saranno utilizzati ai soli fini connessi ai rapporti commerciali tra di noi in essere.
                    <br>
                    Contributo CONAI assolto ove dovuto - Vi preghiamo di controllare i Vs. dati anagrafici, la P. IVA e il Cod. Fiscale. Non ci riteniamo responsabili di eventuali errori.
                </div>
            @endif
        </div>
    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
