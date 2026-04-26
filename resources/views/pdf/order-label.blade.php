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

        .page {
            position: relative;
            width: 567pt;
            height: 312pt;
            overflow: hidden;
        }

        .page.break { page-break-after: always; }

        .label {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
        }

        .left-line {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 2pt;
            background: #000;
        }

        /*
        * Area contenuti.
        * Riduciamo leggermente i margini verticali per recuperare spazio utile,
        * senza cambiare il formato reale dell'etichetta.
        */
        .content {
            position: absolute;
            top: 12pt;
            left: 14pt;
            right: 18pt;
            bottom: 8pt;
            padding: 0;
        }

        /*
        * Area contenuti sopra al footer.
        * Il bottom deve essere coerente con l'altezza reale del footer.
        */
        .content-inner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;

            /*
            * Footer 36pt + 8pt di spazio bianco.
            * Prima era troppo conservativo e lasciava poco spazio al prodotto.
            */
            bottom: 44pt;

            /*
            * Fallback di sicurezza: impedisce al testo di invadere il footer.
            * La riduzione font automatica sotto serve invece a evitare il taglio.
            */
            overflow: hidden;
        }

        table.layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        td {
            padding: 0;
            vertical-align: top;
        }

        .brand {
            width: auto !important;
            font-weight: 700;
            font-size: 58pt;
            line-height: 0.95;
            letter-spacing: -1pt;
            white-space: nowrap;
            text-transform: uppercase;
            padding-right: 10pt;
            overflow: hidden;
        }

        .brand.brand-long {
            font-size: 50pt;
            letter-spacing: -3pt;
        }

        .brand.brand-komodo {
            font-size: 52pt;
            letter-spacing: -3pt;
        }

        .brand-wrap {
            display: block;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            padding-left: 0;
        }

        .dest {
            width: auto !important;
            padding-left: 10pt;
        }

        /*
        * Destinatario leggermente più compatto:
        * con zona + riferimento il blocco alto cresce molto.
        */
        .dest-name {
            font-weight: 800;
            font-size: 24pt;
            line-height: 1.02;
            text-transform: uppercase;
            margin-bottom: 4pt;
            white-space: normal;
            word-wrap: break-word;
        }

        .dest-line {
            font-weight: 600;
            font-size: 15.5pt;
            line-height: 1.06;
            text-transform: uppercase;
            white-space: normal;
            word-wrap: break-word;
        }

        .dest-zone,
        .dest-ref {
            font-weight: 700;
            font-size: 13pt;
            line-height: 1.05;
            text-transform: uppercase;
            white-space: normal;
            word-wrap: break-word;
        }

        /*
        * Prodotto: manteniamo leggibilità, ma riduciamo il blocco base.
        */
        .prod-main {
            font-weight: 800;
            font-size: 26pt;
            line-height: 1.02;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .prod-var {
            margin-top: 4pt;
            font-weight: 700;
            font-size: 21pt;
            line-height: 1.02;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .prod-notes {
            margin-top: 4pt;
            font-weight: 600;
            font-size: 15.5pt;
            line-height: 1.03;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        /*
        * Modalità compatta automatica.
        * Si attiva solo quando il testo dell'etichetta è molto denso.
        */
        .page.label-compact .brand.brand-long {
            font-size: 46pt;
            letter-spacing: -3.2pt;
        }

        .page.label-compact .dest-name {
            font-size: 21pt;
            line-height: 1.00;
            margin-bottom: 3pt;
        }

        .page.label-compact .dest-line {
            font-size: 13.5pt;
            line-height: 1.02;
        }

        .page.label-compact .dest-zone,
        .page.label-compact .dest-ref {
            font-size: 11.5pt;
            line-height: 1.02;
        }

        .page.label-compact .prod-main {
            font-size: 23pt;
            line-height: 1.00;
        }

        .page.label-compact .prod-var {
            margin-top: 3pt;
            font-size: 18.5pt;
            line-height: 1.00;
        }

        .page.label-compact .prod-notes {
            margin-top: 3pt;
            font-size: 13.5pt;
            line-height: 1.00;
        }

        /*
        * Modalità ancora più stretta per casi estremi.
        * Non viene usata sempre, solo quando il punteggio testo è molto alto.
        */
        .page.label-tight .content {
            top: 10pt;
            bottom: 7pt;
        }

        .page.label-tight .content-inner {
            bottom: 44pt;
        }

        .page.label-tight .brand.brand-long {
            font-size: 44pt;
        }

        .page.label-tight .dest-name {
            font-size: 19.5pt;
        }

        .page.label-tight .dest-line {
            font-size: 12.5pt;
        }

        .page.label-tight .dest-zone,
        .page.label-tight .dest-ref {
            font-size: 10.8pt;
        }

        .page.label-tight .prod-main {
            font-size: 19pt;
            line-height: 0.96;
        }

        .page.label-tight .prod-var {
            margin-top: 2pt;
            font-size: 15.5pt;
            line-height: 0.96;
        }

        .page.label-tight .prod-notes {
            margin-top: 2pt;
            font-size: 11.8pt;
            line-height: 0.96;
        }

        /*
        * Footer fissato in basso, ma sollevato dal bordo.
        *
        * Nota importante:
        * con DomPDF è meglio non allineare il testo con vertical-align: bottom
        * dentro una tabella molto bassa, perché può tagliare la parte inferiore
        * delle lettere o dei numeri.
        */
        .footer {
            position: absolute;
            left: 0;
            right: 0;

            /*
            * Solleva il footer dal bordo inferiore dell'area content.
            * Questo evita che AL DIVANI / KOMODO e il numero ordine vengano tagliati.
            */
            bottom: 8pt;

            height: 28pt;
        }

        table.footer-table {
            width: 100%;
            height: 28pt;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.footer-table td {
            padding: 0;

            /*
            * Non usare bottom: DomPDF tende a tagliare il testo.
            * Middle mantiene il footer basso, ma dentro un'area sicura.
            */
            vertical-align: middle;
        }

        .bottom-left {
            font-weight: 800;
            font-size: 17.5pt;
            line-height: 1;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
        }

        .bottom-right {
            text-align: right;
            font-weight: 900;
            font-size: 20pt;
            line-height: 1;
            text-transform: uppercase;
            white-space: nowrap;
        }
    </style>
</head>

<body>
@php
    // KOMODO (6) vs "AL DIVANI" (8 + spazio)
    $isLongBrand = mb_strlen(trim((string)$brand)) > 6;

    // ✅ True solo quando il brand è esattamente "KOMODO"
    $isKomodo = strtoupper(trim((string)$brand)) === 'KOMODO';

    // Larghezza colonna brand in pt: più larga quando il brand è lungo
    // (DomPDF lavora meglio con misure fisse in pt)
    $brandColWidth = $isLongBrand ? 320 : 320;
@endphp

@foreach($labels as $label)
    @php
        /**
         * Calcola quanto è "piena" l'etichetta.
         *
         * Non taglia nessun testo: serve solo per applicare una classe CSS
         * più compatta quando prodotto, variante, zona, riferimento e note colore
         * rischiano di occupare troppo spazio verticale.
         */
        $mainLength  = mb_strlen((string) ($label['main'] ?? ''));
        $varLength   = mb_strlen((string) ($label['var'] ?? ''));
        $notesLength = mb_strlen((string) ($label['notes'] ?? ''));

        /**
         * Zona e riferimento aumentano l'altezza del blocco destinatario,
         * quindi li pesiamo anche se non fanno parte del prodotto.
         */
        $densityScore = $mainLength
            + ($varLength * 0.75)
            + ($notesLength * 1.10)
            + (!empty($shippingZone) ? 18 : 0)
            + (!empty($reference) ? 18 : 0);

        /**
         * Soglie empiriche per il formato 567pt x 312pt.
         * label-compact copre casi lunghi normali.
         * label-tight copre casi estremi.
         */
        $densityClass = '';

        if ($densityScore >= 105) {
            $densityClass = 'label-compact';
        }

        /**
        * Se il prodotto è lungo e sono presenti anche variante/note,
        * usiamo prima la modalità stretta.
        *
        * Questo evita che le ultime righe finiscano sotto al footer.
        */
        if (
            $densityScore >= 138 ||
            (
                $mainLength >= 60 &&
                (!empty($label['var']) || !empty($label['notes']))
            )
        ) {
            $densityClass = 'label-tight';
        }
    @endphp

    <div class="page {{ !$loop->last ? 'break' : '' }} {{ $densityClass }}">
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
                            <td class="brand {{ $isLongBrand ? 'brand-long' : ($isKomodo ? 'brand-komodo' : '') }}">
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
                                
                                @if(!empty($shippingZone))
                                    <div class="dest-zone">ZONA: {{ $shippingZone }}</div>
                                @endif

                                @if(!empty($reference))
                                    <div class="dest-ref">RIF.: {{ $reference }}</div>
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
                                @if(!empty($label['notes']))
                                    <div class="prod-notes">{{ $label['notes'] }}</div>
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
