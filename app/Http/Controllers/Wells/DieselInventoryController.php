<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wells\DeleteOperationalRecordRequest;
use App\Http\Requests\Wells\DieselInventoryReportRequest;
use App\Repositories\SystemPermissionRepository;
use App\Services\Wells\DieselInventoryService;
use App\Services\Wells\InspectorService;
use App\Services\Wells\WellService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Mpdf\Mpdf;

class DieselInventoryController extends Controller
{
    public function __construct(
        private readonly DieselInventoryService $dieselInventoryService,
        private readonly WellService $wellService,
        private readonly InspectorService $inspectorService,
        private readonly SystemPermissionRepository $permissions,
    ) {}

    public function index(DieselInventoryReportRequest $request): Response
    {
        $filters = $this->reportFilters($request);

        return Inertia::render('WellsSystem/DieselInventories/Index', [
            'internalReport' => $this->dieselInventoryService->getInternalTankReport($filters),
            'externalReport' => $this->dieselInventoryService->getInventoryReport($filters),
            'columnSets' => $this->dieselInventoryService->getReportColumns(),
            'unclassifiedWellCount' => $this->dieselInventoryService->getUnclassifiedWellCount(),
            'canDelete' => $this->permissions->hasActionPermission(
                (int) Auth::guard('admin')->id(),
                SystemPermissionRepository::DELETE_DIESEL_INVENTORY,
            ),
            'urls' => [
                'index' => route('wells.diesel-inventories.index', [], false),
                'reportPdf' => route('wells.diesel-inventories.report.pdf', [], false),
                'reportExcel' => route('wells.diesel-inventories.report.excel', [], false),
                'wells' => route('wells.wells.index', [], false),
                'deleteBase' => route('wells.diesel-inventories.index', [], false),
            ],
            'wells' => $this->wellService->getActiveWells(),
            'inspectors' => $this->inspectorService->getAllInspectors(),
            'filters' => $filters,
        ]);
    }

    public function destroy(
        DeleteOperationalRecordRequest $request,
        int $inventory,
    ): RedirectResponse {
        $actorId = (int) Auth::guard('admin')->id();
        abort_unless(
            $this->permissions->hasActionPermission(
                $actorId,
                SystemPermissionRepository::DELETE_DIESEL_INVENTORY,
            ),
            403,
            'ليست لديك صلاحية حذف جرد السولار.'
        );

        $this->dieselInventoryService->deleteInventory(
            $inventory,
            $actorId,
            $this->currentActorName(),
            $request->validated('reason'),
        );

        return back()->with('success', 'تم حذف سجل الجرد بنجاح.');
    }

    public function reportPdf(DieselInventoryReportRequest $request): HttpResponse
    {
        $reportType = $this->reportType($request);
        $html = view('wells.diesel-inventory-report', [
            'report' => $this->dieselInventoryService->getExportReport(
                $reportType,
                $this->reportFilters($request),
                $request->validated('columns', []),
            ),
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
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('pdf', $reportType).'"',
        ]);
    }

    public function reportExcel(DieselInventoryReportRequest $request): HttpResponse
    {
        $reportType = $this->reportType($request);
        $html = view('wells.diesel-inventory-report', [
            'report' => $this->dieselInventoryService->getExportReport(
                $reportType,
                $this->reportFilters($request),
                $request->validated('columns', []),
            ),
            'format' => 'excel',
        ])->render();

        return response("\xEF\xBB\xBF".$html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->reportFileName('xls', $reportType).'"',
        ]);
    }

    private function reportFilters(DieselInventoryReportRequest $request): array
    {
        return Arr::only($request->validated(), [
            'well_oid',
            'inspector_oid',
            'generator_oid',
            'date_from',
            'date_to',
            'tank_group',
            'external_view',
        ]);
    }

    private function reportType(DieselInventoryReportRequest $request): string
    {
        $validated = $request->validated();

        if (! empty($validated['report_type'])) {
            return $validated['report_type'];
        }

        if (($validated['tank_group'] ?? null) === 'internal') {
            return 'internal';
        }

        return ($validated['external_view'] ?? null) === 'inventory'
            ? 'external_inventory'
            : 'external_results';
    }

    private function reportFileName(string $extension, string $reportType): string
    {
        return 'wells-diesel-'.$reportType.'-'.now()->format('Ymd_His').'.'.$extension;
    }

    private function currentActorName(): string
    {
        $user = Auth::guard('admin')->user();

        return $user?->full_name
            ?? $user?->name
            ?? $user?->email
            ?? ('admin#'.(string) ($user?->getAuthIdentifier() ?? 'unknown'));
    }
}
