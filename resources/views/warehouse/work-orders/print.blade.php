<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Stampa Buono</title>
    <style>
        html, body { height: 100%; margin: 0; }
        iframe { width: 100%; height: 100%; border: 0; }
    </style>
</head>
<body>
    <iframe id="pdfFrame" src="{{ $pdfUrl }}"></iframe>

    <script>
        const frame = document.getElementById('pdfFrame');
        frame.addEventListener('load', () => {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); }
            catch (e) { console.error('Auto-print non disponibile:', e); }
        });
    </script>
</body>
</html>
