<?php

declare(strict_types=1);

namespace App\Http\Controllers\Wells;
use App\Http\Controllers\Controller;

use App\Http\Requests\Wells\StoreWellRequest;
use App\Http\Requests\Wells\UpdateWellRequest;
use App\Services\Wells\WellService;
use App\Services\Wells\TeamService;
use App\Services\Wells\GeneratorService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WellController extends Controller
{
    public function __construct(
        private readonly WellService $wellService,
        private readonly TeamService $teamService,
        private readonly GeneratorService $generatorService,
    ) {
    }

    public function index(\Illuminate\Http\Request $request): Response
    {
        $filters = $request->only(['search', 'quarter_oid', 'is_active']);

        return Inertia::render('WellsSystem/Wells/Index', [
            'wells' => $this->wellService->getPaginatedWells(15, $filters),
            'filters' => $filters,
            'quarters' => $this->wellService->getQuarters(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('WellsSystem/Wells/Create', [
            'quarters' => $this->wellService->getQuarters(),
            'generators' => $this->generatorService->getActiveGenerators(),
        ]);
    }

    public function store(StoreWellRequest $request): RedirectResponse
    {
        $this->wellService->createWell($this->validatedWellData($request));

        return redirect()->route('wells.wells.index')
            ->with('success', 'تم إنشاء البئر بنجاح');
    }

    public function show(int $well): Response
    {
        return Inertia::render('WellsSystem/Wells/Edit', [
            'well' => $this->wellService->getWell($well),
            'quarters' => $this->wellService->getQuarters(),
            'generators' => $this->generatorService->getActiveGenerators(),
            'readonly' => true,
        ]);
    }

    public function edit(int $well): Response
    {
        $wellModel = $this->wellService->getWell($well);
        $hasTransactions = $wellModel->readings()->exists() || $wellModel->dieselInventories()->exists();

        return Inertia::render('WellsSystem/Wells/Edit', [
            'well' => $wellModel,
            'hasTransactions' => $hasTransactions,
            'quarters' => $this->wellService->getQuarters(),
            'generators' => $this->generatorService->getActiveGenerators(),
        ]);
    }

    public function update(UpdateWellRequest $request, int $well): RedirectResponse
    {
        $this->wellService->updateWell($well, $this->validatedWellData($request));

        return redirect()->route('wells.wells.index')
            ->with('success', 'تم تحديث البئر بنجاح');
    }

    public function destroy(int $well): RedirectResponse
    {
        try {
            $this->wellService->deleteWell($well);
            return redirect()->route('wells.wells.index')
                ->with('success', 'تم حذف البئر بنجاح');
        } catch (\Exception $e) {
            return redirect()->route('wells.wells.index')
                ->with('error', $e->getMessage());
        }
    }

    public function map(): Response
    {
        return Inertia::render('WellsSystem/Wells/Map', [
            'wells' => $this->wellService->getWellsForMap(),
        ]);
    }

    private function validatedWellData(StoreWellRequest|UpdateWellRequest $request): array
    {
        $data = $request->validated();

        if ($request->has('generators')) {
            $data['generators'] = $request->input('generators', []);
        }

        if ($request->has('GENERATORS')) {
            $data['GENERATORS'] = $request->input('GENERATORS', []);
        }

        return $data;
    }
}
