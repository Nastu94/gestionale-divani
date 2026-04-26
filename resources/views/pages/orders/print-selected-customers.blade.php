{{-- resources/views/pages/orders/print-selected-customers.blade.php --}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">

    <title>Stampa ordini cliente selezionati</title>

    <style>
        /*
         * Layout base della pagina stampabile.
         * Manteniamo CSS semplice perché deve essere stabile in stampa browser.
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

        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            border-bottom: 1px solid #111827;
            padding-bottom: 8px;
        }

        .print-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
        }

        .print-meta {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
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

        .status {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9.5px;
        }

        .production-note {
            display: block;
            margin-top: 2px;
            font-size: 9px;
            font-weight: 600;
        }

        /*
         * In stampa nascondiamo il pulsante manuale.
         */
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
            <h1 class="print-title">Ordini cliente selezionati</h1>
        </div>

        <div class="print-meta">
            <div>Righe selezionate: {{ $orders->count() }}</div>
            <div>Stampato il: {{ $printedAt->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Ordine #</th>
                <th style="width: 18%;">Cliente</th>
                <th style="width: 24%;">Indirizzo spedizione</th>
                <th style="width: 13%;">Riferimento</th>
                <th style="width: 10%;">Data ordine</th>
                <th style="width: 10%;">Data consegna</th>
                <th style="width: 9%;" class="text-right">Valore (€)</th>
                <th style="width: 8%;">Stato</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($orders as $order)
                @php
                    /*
                     * Nome cliente coerente con la tabella principale:
                     * cliente standard oppure cliente occasionale.
                     */
                    $customerName = $order->customer
                        ? $order->customer->company
                        : ($order->occasionalCustomer->company ?? '—');

                    /*
                     * Stato ordine in formato testuale, adatto alla stampa.
                     */
                    if ((int) $order->status === 0 && $order->reason === null) {
                        $statusLabel = 'Non confermato';
                    } elseif ((int) $order->status === 0 && $order->reason !== null) {
                        $statusLabel = 'Rifiutato';
                    } elseif ((int) $order->status === 1) {
                        $statusLabel = 'Confermato';
                    } else {
                        $statusLabel = '—';
                    }
                @endphp

                <tr>
                    <td class="text-center whitespace-nowrap">
                        {{ $order->orderNumber->number ?? $order->id }}

                        @if ($order->has_started_prod)
                            <span class="production-note">Produzione in corso</span>
                        @endif
                    </td>

                    <td>
                        {{ $customerName }}
                    </td>

                    <td>
                        {{ $order->shipping_address ?? '—' }}
                    </td>

                    <td>
                        {{ $order->reference ?? '—' }}
                    </td>

                    <td class="whitespace-nowrap">
                        {{ $order->ordered_at?->format('d/m/Y') ?? '—' }}
                    </td>

                    <td class="whitespace-nowrap">
                        {{ $order->delivery_date?->format('d/m/Y') ?? '—' }}
                    </td>

                    <td class="text-right whitespace-nowrap">
                        {{ number_format((float) $order->total, 2, ',', '.') }}
                    </td>

                    <td>
                        <span class="status">{{ $statusLabel }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <script>
        /*
         * Apre automaticamente la finestra di stampa quando la pagina è pronta.
         * Il piccolo delay evita che il browser stampi prima del rendering completo.
         */
        window.addEventListener('load', function () {
            window.setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>