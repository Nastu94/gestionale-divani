{{-- resources/views/pdf/order-label.blade.php --}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        /* Carta: deve matchare setPaper([0,0,567,312]) */
        @page { size: 567pt 312pt; margin: 0; }

        html, body {
            margin: 0;
            padding: 0;
            font-family: "DejaVu Sans", sans-serif;
        }

        /* Wrapper pagina: dimensione fissa */
        .page {
            position: relative;
            width: 567pt;
            height: 312pt;
            overflow: hidden; /* niente “sfori” che creano pagine extra */
        }
        .page.break { page-break-after: always; }

        /* Label “inset 0”: NON usare height/width + padding insieme */
        .label {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
        }

        /* Linea nera a sinistra come proforma */
        .left-line {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 2pt;
            background: #000;
        }

        /* Area contenuti: usa inset invece del padding (DomPDF calcola meglio l'altezza) */
        .content {
            position: absolute;
            top: 18pt;
            left: 14pt;
            right: 22pt;
            bottom: 14pt;

            padding: 0;           /* IMPORTANT: niente padding qui */
        }

        /* Wrapper contenuti: lascia spazio riservato in basso per footer + gap */
        .content-inner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;

            /* 40pt footer + 12pt gap sopra footer (spazio bianco garantito) */
            bottom: 52pt;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        /* 3 bande: TOP / PRODOTTO / SPACER / FOOTER */
        tr.row-top    { height: auto; }   /* auto: prende solo lo spazio che serve */
        tr.row-prod   { height: auto; }   /* auto: prende solo lo spazio che serve */
        tr.row-spacer { height: 100%; }   /* riempie TUTTO lo spazio residuo */
        tr.row-footer { height: 40pt; }   /* footer fisso */

        /* Spacer “invisibile”: evita che &nbsp; crei una riga visibile */
        tr.row-spacer td {
            font-size: 0;
            line-height: 0;
            padding: 0;
        }

        td { padding: 0; vertical-align: top; }

        /* TOP */
        .brand {
            width: auto !important;
            font-weight: 700;
            font-size: 58pt;       
            line-height: 0.95;
            letter-spacing: -1pt;
            white-space: nowrap;
            text-transform: uppercase;
            padding-right: 10pt;
            overflow: hidden; /* evita che “invada” la colonna destinatario */
        }

        /* AL DIVANI è più lungo */
        .brand.brand-long {
            font-size: 50pt;
            letter-spacing: -3pt;
        }

        /* Wrapper che “clippa” davvero */
        .brand-wrap{
            display: block;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;                     /* qui DomPDF lo rispetta molto meglio */
            padding-left: 4pt;                    /* evita che la prima lettera tocchi la linea */
        }

        /* Stacca visivamente la colonna destinatario dal brand */
        .dest{
            width: auto !important;               /* colgroup decide la larghezza */
            padding-left: 10pt;                   /* gap: evita l’effetto “AL DIVANIKEA” */
        }

        .dest-name {
            font-weight: 800;
            font-size: 26pt;          /* leggermente più piccolo: meno tagli */
            line-height: 1.05;
            text-transform: uppercase;
            margin-bottom: 6pt;

            white-space: normal;
            word-wrap: break-word;
        }

        .dest-line {
            font-weight: 600;
            font-size: 16.5pt;        /* leggermente più piccolo: l’indirizzo non si tronca */
            line-height: 1.12;
            text-transform: uppercase;

            white-space: normal;
            word-wrap: break-word;
        }

        /* PRODOTTO */
        .prod-main {
            font-weight: 800;
            font-size: 28pt;
            line-height: 1.06;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .prod-var {
            margin-top: 6pt;
            font-weight: 700;
            font-size: 24pt;
            line-height: 1.05;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        /* FOOTER */
        /* Footer sempre a fondo area content */
        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;

            height: 55pt; /* altezza footer */
        }

        /* Tabellina footer per allineare SX/DX */
        table.footer-table {
            width: 100%;
            height: 55pt;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.footer-table td {
            padding: 0;
            vertical-align: bottom;
        }

        .bottom-left {
            font-weight: 800;
            font-size: 20pt;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
        }

        .bottom-right {
            text-align: right;
            font-weight: 900;
            font-size: 22pt;
            text-transform: uppercase;
            white-space: nowrap;
        }
    </style>
</head>

<body>
@php
    // KOMODO (6) vs "AL DIVANI" (8 + spazio)
    $isLongBrand = mb_strlen(trim((string)$brand)) > 6;

    // Larghezza colonna brand in pt: più larga quando il brand è lungo
    // (DomPDF lavora meglio con misure fisse in pt)
    $brandColWidth = $isLongBrand ? 320 : 300;
@endphp

@foreach($labels as $label)
    <div class="page {{ !$loop->last ? 'break' : '' }}">
        <div class="label">
            <div class="left-line"></div>

            <div class="content">
                {{-- CONTENUTI: area che lascia spazio al footer --}}
                <div class="content-inner">
                    <table class="layout">
                        <colgroup>
                            <col style="width: {{ $brandColWidth }}pt;">
                            <col style="width: auto;">
                        </colgroup>
                        {{-- TOP --}}
                        <tr class="row-top">
                            <td class="brand {{ $isLongBrand ? 'brand-long' : '' }}">
                                {{-- Wrapper: overflow hidden funziona meglio qui che sul <td> in DomPDF --}}
                                <div class="brand-wrap">{{ $brand }}</div>
                            </td>
                            <td class="dest">
                                <div class="dest-name">{{ $name }}</div>

                                @if(!empty($street))
                                    <div class="dest-line">{{ $street }}</div>
                                @endif

                                @if(!empty($cityLine))
                                    <div class="dest-line">{{ $cityLine }}</div>
                                @endif
                            </td>
                        </tr>

                        {{-- PRODOTTO --}}
                        <tr class="row-prod">
                            <td colspan="2">
                                <div class="prod-main">{{ $label['main'] }}</div>
                                @if(!empty($label['var']))
                                    <div class="prod-var">{{ $label['var'] }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- FOOTER: sempre in fondo --}}
                <div class="footer">
                    <table class="footer-table">
                        <tr>
                            <td class="bottom-left">{{ $brandCity }}</td>
                            <td class="bottom-right">{{ $orderNo }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endforeach

</body>
</html>
