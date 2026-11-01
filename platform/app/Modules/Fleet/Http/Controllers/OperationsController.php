<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Http\Requests\AppUsageRequest;
use App\Modules\Fleet\Http\Requests\CatalogItemRequest;
use App\Modules\Fleet\Http\Requests\ReportRequest;
use App\Modules\Fleet\Repositories\CatalogRepository;
use App\Modules\Fleet\Services\DriverAppUsageService;
use App\Modules\Fleet\Services\FleetLiveService;
use App\Modules\Fleet\Services\FleetReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Live monitoring, app adoption, reports and catalogs: thin delegation only. */
class OperationsController extends Controller
{
    public function live(Request $request, FleetLiveService $live): Response
    {
        return Inertia::render('Fleet/Live', $live->overview((int) $request->user('admin')->getAuthIdentifier()));
    }

    public function app(AppUsageRequest $request, DriverAppUsageService $usage): Response
    {
        return Inertia::render('Fleet/App', $usage->overview($request->validated(), $request->url()));
    }

    public function reports(ReportRequest $request, FleetReportService $reports): Response
    {
        return Inertia::render('Fleet/Reports', [
            ...$reports->screen((int) $request->user('admin')->getAuthIdentifier(), $request->validated()),
            'filters' => $request->validated(),
            'csvUrl' => route('fleet.reports.csv', $request->validated()),
        ]);
    }

    public function reportsCsv(ReportRequest $request, FleetReportService $reports): StreamedResponse
    {
        return $reports->csv((int) $request->user('admin')->getAuthIdentifier(), $request->validated());
    }

    public function catalogs(Request $request, CatalogRepository $catalogs): Response
    {
        $type = $request->query('type') === 'mission-types' ? 'mission-types' : 'locations';

        return Inertia::render('Fleet/Catalogs', ['type' => $type, 'items' => $catalogs->all($type)]);
    }

    public function saveCatalog(CatalogItemRequest $request, CatalogRepository $catalogs, string $type, ?int $id = null): RedirectResponse
    {
        $catalogs->save($type, $id, $request->attributesForSave());

        return back()->with('success', __('common.saved'));
    }
}
