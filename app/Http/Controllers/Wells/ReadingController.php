<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\ReadingReportRequest;
use App\Services\Wells\InspectorService;
use App\Services\Wells\ReadingService;
use App\Services\Wells\WellService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class ReadingController extends Controller
{
    public function __construct(
        private readonly ReadingService $readingService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
    ) {}

    public function index(ReadingReportRequest $request): Response
    {
        $filters = $this->readingFilters($request);

        return Inertia::render('WellsSystem/Readings/Index', [
            'readings' => $this->readingService->getPaginatedReadings(15, $filters),
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
            'statistics' => $this->readingService->getStatistics(),
            'reportColumns' => $this->readingService->getReportColumns(),
            'reportPreview' => Inertia::optional(
                fn (): array => $this->readingService->getProductionReport($filters)
            ),
            'urls' => [
                'index' => route('wells.readings.index', [], false),
                'reportPdf' => route('wells.readings.report.pdf', [], false),
                'reportExcel' => route('wells.readings.report.excel', [], false),
            ],
        ]);
    }

    public function show(int $reading): Response
    {
        return Inertia::render('WellsSystem/Readings/Show', [
            'reading' => $this->readingService->getReading($reading),
        ]);
    }

    public function reportPdf(ReadingReportRequest $request): HttpResponse
    {
        $report = $this->readingService->getExportReport(
            $this->readingFilters($request),
            $request->validated('columns', []),
        );
        $html = view('wells.readings-report', [
            'report' => $report,
            'format' => 'pdf',
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A2-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'shrink_tables_to_fit' => 1,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('pdf').'"',
        ]);
    }

    public function reportExcel(ReadingReportRequest $request): HttpResponse
    {
        $html = view('wells.readings-report', [
            'report' => $this->readingService->getExportReport(
                $this->readingFilters($request),
                $request->validated('columns', []),
            ),
            'format' => 'excel',
        ])->render();

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('xls').'"',
        ]);
    }

    private function readingFilters(ReadingReportRequest $request): array
    {
        return Arr::only($request->validated(), [
            'well_oid',
            'inspector_oid',
            'quality',
            'date_from',
            'date_to',
        ]);
    }

    private function reportFileName(string $extension): string
    {
        return 'wells-production-report-'.now()->format('Ymd_His').'.'.$extension;
    }
}
