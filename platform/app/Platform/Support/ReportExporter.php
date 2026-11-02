<?php

declare(strict_types=1);

namespace App\Platform\Support;

use Illuminate\Http\Response;
use Mpdf\Mpdf;

/**
 * PDF (mPDF) and Excel (HTML table, UTF-8 BOM) downloads from one Blade view —
 * the same export mechanism the legacy controllers used, in one place.
 */
class ReportExporter
{
    public function download(string $view, array $data, string $format, string $fileName, string $paper = 'A3-L'): Response
    {
        $html = view($view, [...$data, 'format' => $format === 'excel' ? 'excel' : 'pdf'])->render();

        if ($format === 'excel') {
            return response("\xEF\xBB\xBF".$html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'.xls"',
            ]);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => $paper,
            'margin_left' => 6, 'margin_right' => 6, 'margin_top' => 7, 'margin_bottom' => 7,
            'shrink_tables_to_fit' => 3, 'autoScriptToLang' => true, 'autoLangToFont' => true,
            'tempDir' => storage_path('framework/cache/mpdf'),
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'.pdf"',
        ]);
    }
}
