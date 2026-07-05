<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Services\Wells\ReadingService;
use App\Services\Wells\WellService;
use App\Services\Wells\InspectorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class ReadingController extends Controller
{
    public function __construct(
        private readonly ReadingService $readingService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $this->readingFilters($request);

        return Inertia::render('WellsSystem/Readings/Index', [
            'readings' => $this->readingService->getPaginatedReadings(15, $filters),
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
            'statistics' => $this->readingService->getStatistics(),
        ]);
    }

    public function show(int $reading): Response
    {
        return Inertia::render('WellsSystem/Readings/Show', [
            'reading' => $this->readingService->getReading($reading),
        ]);
    }

    public function reportPdf(Request $request)
    {
        $report = $this->readingService->getProductionReport($this->readingFilters($request));
        $html = view('wells.readings-report', [
            'report' => $report,
            'format' => 'pdf',
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->reportFileName('pdf') . '"',
        ]);
    }

    public function reportExcel(Request $request)
    {
        $html = view('wells.readings-report', [
            'report' => $this->readingService->getProductionReport($this->readingFilters($request)),
            'format' => 'excel',
        ])->render();

        return response("\xEF\xBB\xBF" . $html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->reportFileName('xls') . '"',
        ]);
    }

    private function readingFilters(Request $request): array
    {
        return $request->validate([
            'well_oid' => ['nullable', 'integer'],
            'inspector_oid' => ['nullable', 'integer'],
            'quality' => ['nullable', 'string', 'in:excellent,good,acceptable,poor'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);
    }

    private function reportFileName(string $extension): string
    {
        return 'wells-production-report-' . now()->format('Ymd_His') . '.' . $extension;
    }
}
