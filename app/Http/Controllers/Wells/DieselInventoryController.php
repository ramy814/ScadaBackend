<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Services\Wells\DieselInventoryService;
use App\Services\Wells\InspectorService;
use App\Services\Wells\WellService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class DieselInventoryController extends Controller
{
    public function __construct(
        private readonly DieselInventoryService $dieselInventoryService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->inventoryFilters($request);

        return Inertia::render('WellsSystem/DieselInventories/Index', [
            'inventories' => $this->dieselInventoryService->getPaginatedInventories(15, $filters),
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
            'statistics' => $this->dieselInventoryService->getStatistics(),
        ]);
    }

    public function reportPdf(Request $request)
    {
        $html = view('wells.diesel-inventory-report', [
            'report' => $this->dieselInventoryService->getInventoryReport($this->inventoryFilters($request)),
            'format' => 'pdf',
        ])->render();
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
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('pdf').'"',
        ]);
    }

    public function reportExcel(Request $request)
    {
        $html = view('wells.diesel-inventory-report', [
            'report' => $this->dieselInventoryService->getInventoryReport($this->inventoryFilters($request)),
            'format' => 'excel',
        ])->render();

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('xls').'"',
        ]);
    }

    private function inventoryFilters(Request $request): array
    {
        return $request->validate([
            'well_oid' => ['nullable', 'integer'],
            'inspector_oid' => ['nullable', 'integer'],
            'generator_oid' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function reportFileName(string $extension): string
    {
        return 'wells-diesel-inventory-report-'.now()->format('Ymd_His').'.'.$extension;
    }
}
