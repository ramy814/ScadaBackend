<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Wells\Http\Requests\DeleteOperationalRecordRequest;
use App\Modules\Wells\Http\Requests\DieselInventoryReportRequest;
use App\Modules\Wells\Http\Requests\MonthlyDieselReportRequest;
use App\Modules\Wells\Http\Requests\ReadingReportRequest;
use App\Modules\Wells\Services\DieselInventoryService;
use App\Modules\Wells\Services\InspectorService;
use App\Modules\Wells\Services\MonthlyDieselReportService;
use App\Modules\Wells\Services\ReadingService;
use App\Modules\Wells\Services\WellService;
use App\Modules\Wells\Services\WellsService;
use App\Platform\Access\SystemPermissionRepository;
use App\Platform\Support\ReportExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

/** Readings, production report, diesel and monthly diesel: thin delegation to ported services. */
class ReportsController extends Controller
{
    private const READING_FILTERS = ['well_oid', 'inspector_oid', 'quality', 'date_from', 'date_to'];

    private const DIESEL_FILTERS = ['well_oid', 'inspector_oid', 'generator_oid', 'date_from', 'date_to', 'tank_group', 'external_view'];

    public function __construct(
        private readonly ReadingService $readings,
        private readonly DieselInventoryService $diesel,
        private readonly MonthlyDieselReportService $monthly,
        private readonly WellService $wells,
        private readonly InspectorService $inspectors,
        private readonly WellsService $presenter,
        private readonly SystemPermissionRepository $permissions,
        private readonly ReportExporter $exporter,
    ) {}

    public function readings(ReadingReportRequest $request): Response
    {
        $filters = Arr::only($request->validated(), self::READING_FILTERS);

        return Inertia::render('Wells/Readings', [
            ...$this->presenter->readings(['search' => $request->query('search'), 'date_from' => $filters['date_from'] ?? null, 'date_to' => $filters['date_to'] ?? null]),
            'filters' => [...$filters, 'search' => $request->query('search')],
            'reportColumns' => $this->readings->getReportColumns(),
            'report' => Inertia::optional(fn (): array => $this->readings->getProductionReport($filters)),
            'options' => $this->options(),
            'canDelete' => $this->can($request, SystemPermissionRepository::DELETE_WELL_READING),
        ]);
    }

    public function readingsExport(ReadingReportRequest $request, string $format): HttpResponse
    {
        return $this->exporter->download('wells.readings-report', [
            'report' => $this->readings->getExportReport(Arr::only($request->validated(), self::READING_FILTERS), $request->validated('columns', [])),
        ], $format, 'wells-production-report-'.now()->format('Ymd_His'), 'A2-L');
    }

    public function deleteReading(DeleteOperationalRecordRequest $request, int $reading): RedirectResponse
    {
        abort_unless($this->can($request, SystemPermissionRepository::DELETE_WELL_READING), 403, __('wells.errors.delete_reading'));
        $this->readings->deleteReading($reading, $this->actorId($request), $request->user('admin')->displayName(), $request->validated('reason'));

        return back()->with('success', __('wells.deleted.reading'));
    }

    public function diesel(DieselInventoryReportRequest $request): Response
    {
        $filters = Arr::only($request->validated(), self::DIESEL_FILTERS);

        return Inertia::render('Wells/Diesel', [
            'internalReport' => $this->diesel->getInternalTankReport($filters),
            'externalReport' => $this->diesel->getInventoryReport($filters),
            'columnSets' => $this->diesel->getReportColumns(),
            'unclassifiedWellCount' => $this->diesel->getUnclassifiedWellCount(),
            'filters' => $filters,
            'options' => $this->options(),
            'canDelete' => $this->can($request, SystemPermissionRepository::DELETE_DIESEL_INVENTORY),
        ]);
    }

    public function dieselExport(DieselInventoryReportRequest $request, string $format): HttpResponse
    {
        $validated = $request->validated();
        $type = $validated['report_type'] ?? match (true) {
            ($validated['tank_group'] ?? null) === 'internal' => 'internal',
            ($validated['external_view'] ?? null) === 'inventory' => 'external_inventory',
            default => 'external_results',
        };

        return $this->exporter->download('wells.diesel-inventory-report', [
            'report' => $this->diesel->getExportReport($type, Arr::only($validated, self::DIESEL_FILTERS), $validated['columns'] ?? []),
        ], $format, 'wells-diesel-'.$type.'-'.now()->format('Ymd_His'));
    }

    public function deleteInventory(DeleteOperationalRecordRequest $request, int $inventory): RedirectResponse
    {
        abort_unless($this->can($request, SystemPermissionRepository::DELETE_DIESEL_INVENTORY), 403, __('wells.errors.delete_inventory'));
        $this->diesel->deleteInventory($inventory, $this->actorId($request), $request->user('admin')->displayName(), $request->validated('reason'));

        return back()->with('success', __('wells.deleted.inventory'));
    }

    public function monthly(MonthlyDieselReportRequest $request): Response
    {
        $filters = $this->monthlyFilters($request);

        return Inertia::render('Wells/DieselMonthly', [
            'report' => $this->monthly->getReport($filters['tank_group'], $filters['month'], $filters),
            'columnSets' => $this->monthly->getReportColumns(),
            'filters' => $filters,
            'options' => $this->options(),
        ]);
    }

    public function monthlyExport(MonthlyDieselReportRequest $request, string $format): HttpResponse
    {
        $filters = $this->monthlyFilters($request);

        return $this->exporter->download('wells.diesel-inventory-report', [
            'report' => $this->monthly->getExportReport($filters['tank_group'], $filters['month'], $filters, $request->validated('columns', [])),
        ], $format, 'wells-diesel-monthly-'.$filters['tank_group'].'-'.$filters['month']);
    }

    /** Same defaults as the legacy controller: current month, Daily Tank group. */
    private function monthlyFilters(MonthlyDieselReportRequest $request): array
    {
        $validated = $request->validated();

        return [
            ...Arr::only($validated, ['well_oid', 'generator_oid']),
            'month' => $validated['month'] ?? now()->format('Y-m'),
            'tank_group' => ($validated['tank_group'] ?? null) === 'external' ? 'external' : 'internal',
        ];
    }

    private function options(): array
    {
        return [
            'wells' => $this->wells->getActiveWells()->map(fn ($w): array => ['oid' => (int) $w->getKey(), 'name' => $w->name ?? $w->NAME])->values(),
            'inspectors' => $this->inspectors->getAllInspectors()->map(fn ($i): array => ['oid' => (int) $i->getKey(), 'name' => $i->name ?? $i->NAME])->values(),
        ];
    }

    private function can(Request $request, string $action): bool
    {
        return $this->permissions->hasActionPermission($this->actorId($request), $action);
    }

    private function actorId(Request $request): int
    {
        return (int) $request->user('admin')->getAuthIdentifier();
    }
}
