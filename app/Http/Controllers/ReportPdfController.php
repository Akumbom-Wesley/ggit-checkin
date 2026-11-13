<?php

namespace App\Http\Controllers;

use App\Services\AttendanceReport;
use Barryvdh\DomPDF\Facade\Pdf;

/** PDF: summary on page 1, then the full attendance list. */
class ReportPdfController extends Controller
{
    public function __invoke(AttendanceReport $report)
    {
        $pdf = Pdf::loadView('pdf.report', $report->build())
            ->setPaper('a4', 'portrait');

        return $pdf->download('GGIT-Report-'.now()->format('Y-m-d-Hi').'.pdf');
    }
}