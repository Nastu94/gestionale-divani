{{-- resources/views/warehouse/ddt-print.blade.php --}}
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Stampa DDT {{ $ddt->number }}</title>

    <style>
        /* iframe a pieno schermo */
        html, body { height: 100%; margin: 0; }
        iframe { width: 100%; height: 100%; border: 0; }
    </style>
</head>
<body>
    <iframe id="pdfFrame" src="{{ $pdfUrl }}"></iframe>

    <script>
        /**
         * Auto-print:
         * - aspettiamo il load dell'iframe
         * - poi chiamiamo print() sul contenuto
         */
        const frame = document.getElementById('pdfFrame');

        frame.addEventListener('load', () => {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                // Fallback: se il browser blocca, l’utente stampa manualmente
                console.error('Auto-print non disponibile:', e);
            }
        });
    </script>
</body>
</html>
