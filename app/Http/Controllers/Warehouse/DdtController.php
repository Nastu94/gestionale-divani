<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\Ddt;
use App\Services\Ddt\DdtPdfService;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller DDT:
 * - print(): apre pagina stampa (iframe + auto-print)
 * - pdf(): stream PDF
 */
class DdtController extends Controller
{
    /**
     * Pagina wrapper che apre la finestra di stampa.
     */
    public function print(Ddt $ddt): View
    {
        /* Rotta firmata verso il PDF (validità breve) */
        $pdfUrl = URL::temporarySignedRoute(
            'warehouse.ddt.pdf',
            now()->addMinutes(5),
            ['ddt' => $ddt->id]
        );

        return view('warehouse.ddt-print', [
            'ddt' => $ddt,
            'pdfUrl' => $pdfUrl,
        ]);
    }

    /**
     * Endpoint che streamma il PDF.
     */
    public function pdf(Ddt $ddt, DdtPdfService $pdf): Response
    {
        return $pdf->stream($ddt);
    }
}
