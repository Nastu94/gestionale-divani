{{-- resources/views/pages/warehouse/exits-print-selected.blade.php --}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">

    <title>Stampa uscite selezionate</title>

    <style>
        /*
         * Pagina orizzontale perché la tabella ha molte colonne.
         */
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #111827;
        }

        .no-print {
            margin-bottom: 12px;
        }

        .print-button {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #111827;
            background: #ffffff;
            color: #111827;
            font-size: 12px;
            cursor: pointer;
        }

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            border-bottom: 1px solid #111827;
            padding-bottom: 8px;
        }

        .print-title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .print-subtitle {
            margin-top: 4px;
            font-size: 12px;
            color: #374151;
        }

        .print-meta {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead {
            display: table-header-group;
        }

        th {
            background: #e5e7eb;
            border: 1px solid #9ca3af;
            padding: 5px 4px;
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            font-size: 10px;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 5px 4px;
            vertical-align: top;
            font-size: 10.5px;
            line-height: 1.25;
            word-wrap: break-word;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .whitespace-nowrap {
            white-space: nowrap;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="no-print">
        <button type="button" class="print-button" onclick="window.print()">
            Stampa
        </button>
    </div>

    <div class="print-header">
        <div>
            <h1 class="print-title">Uscite di magazzino selezionate</h1>
            <div class="print-subtitle">
                Fase: {{ $phaseLabel }}
            </div>
        </div>

        <div class="print-meta">
            <div>Righe selezionate: {{ $rows->count() }}</div>
            <div>Stampato il: {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 18%;">Cliente</th>
                <th style="width: 13%;">Zona spedizione</th>
                <th style="width: 9%;">Nr. ordine</th>
                <th style="width: 24%;">Prodotto</th>
                <th style="width: 10%;">Data ordine</th>
                <th style="width: 10%;">Consegna</th>
                <th style="width: 8%;" class="text-right">Valore €</th>
                <th style="width: 8%;" class="text-right">Q.ty fase</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        {{ $row->customer ?? '—' }}
                    </td>

                    <td>
                        {{ $row->shipping_zone ?? '—' }}
                    </td>

                    <td class="text-center whitespace-nowrap">
                        {{ $row->order_number ?? '—' }}
                    </td>

                    <td>
                        {{ $row->product_name ?? '—' }}
                    </td>

                    <td class="whitespace-nowrap">
                        {{ !empty($row->order_date) ? \Carbon\Carbon::parse($row->order_date)->format('d/m/Y') : '—' }}
                    </td>

                    <td class="whitespace-nowrap">
                        {{ !empty($row->delivery_date) ? \Carbon\Carbon::parse($row->delivery_date)->format('d/m/Y') : '—' }}
                    </td>

                    <td class="text-right whitespace-nowrap">
                        € {{ number_format((float) $row->value, 2, ',', '.') }}
                    </td>

                    <td class="text-right whitespace-nowrap">
                        {{ rtrim(rtrim(number_format((float) $row->qty_in_phase, 4, ',', '.'), '0'), ',') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        /*
         * Avvia automaticamente la stampa quando la pagina è pronta.
         */
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>