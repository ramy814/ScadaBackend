<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\MonthlyDieselReportRequest;
use App\Services\Wells\MonthlyDieselReportService;
use App\Services\Wells\WellService;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class MonthlyDieselReportController extends Controller
{
    public function __construct(
        private readonly MonthlyDieselReportService $reportService,
        private readonly WellService $wellService,
    ) {}

    public function index(MonthlyDieselReportRequest $request): Response
    {
        $filters = $this->filters($request);
        $month = $filters['month'];
        $tankGroup = $filters['tank_group'];

        return Inertia::render('WellsSystem/DieselInventories/Monthly', [
            'report' => $this->reportService->getReport($tankGroup, $month, $filters),
            'columnSets' => $this->reportService->getReportColumns(),
            'wells' => $this->wellService->getActiveWells(),
            'filters' => $filters,
            'urls' => [
                'index' => route('wells.diesel-inventories.monthly.index', [], false),
                'report' => route('wells.diesel-inventories.monthly.export', ['format' => '__FORMAT__'], false),
                'inventory' => route('wells.diesel-inventories.index', [], false),
            ],
        ]);
    }

    public function export(MonthlyDieselReportRequest $request, string $format): HttpResponse
    {
        abort_unless(in_array($format, ['pdf', 'excel'], true), 404);
        $filters = $this->filters($request);
        $report = $this->reportService->getExportReport(
            $filters['tank_group'],
            $filters['month'],
            $filters,
            $request->validated('columns', []),
        );
        $html = view('wells.diesel-inventory-report', [
            'report' => $report,
            'format' => $format,
        ])->render();

        if ($format === 'excel') {
            return response("\xEF\xBB\xBF".$html, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$this->fileName('xls', $filters).'"',
            ]);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A3-L',
            'margin_left' => 6,
            'margin_right' => 6,
            'margin_top' => 7,
            'margin_bottom' => 7,
            'shrink_tables_to_fit' => 3,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->fileName('pdf', $filters).'"',
        ]);
    }

    /** @return array{month: string, tank_group: string, well_oid?: mixed, generator_oid?: mixed} */
    private function filters(MonthlyDieselReportRequest $request): array
    {
        $validated = $request->validated();

        return array_merge(
            Arr::only($validated, ['well_oid', 'generator_oid']),
            [
                'month' => $validated['month'] ?? now()->format('Y-m'),
                'tank_group' => ($validated['tank_group'] ?? null) === 'external' ? 'external' : 'internal',
            ],
        );
    }

    private function fileName(string $extension, array $filters): string
    {
        return 'wells-diesel-monthly-'.$filters['tank_group'].'-'.$filters['month'].'.'.$extension;
    }
}
