<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Repositories\Wells\WellRepository;
use App\Repositories\Wells\TeamRepository;
use App\Repositories\Wells\InspectorRepository;
use App\Repositories\Wells\ReadingRepository;
use App\Repositories\Wells\DieselInventoryRepository;

class DashboardService
{
    public function __construct(
        private readonly WellRepository $wellRepository,
        private readonly TeamRepository $teamRepository,
        private readonly InspectorRepository $inspectorRepository,
        private readonly ReadingRepository $readingRepository,
        private readonly DieselInventoryRepository $dieselInventoryRepository,
    ) {
    }

    public function getStats(): array
    {
        $readingStats = $this->readingRepository->getStats();
        $dieselStats = $this->dieselInventoryRepository->getStats();

        return [
            'totalWells' => $this->wellRepository->getTotalCount(),
            'totalTeams' => $this->teamRepository->getActiveCount(),
            'totalInspectors' => $this->inspectorRepository->getActiveCount(),
            'totalReadings' => $readingStats['total'] ?? 0,
            'pendingReadings' => 0, // Placeholder
            'todayReadings' => $readingStats['today'] ?? 0,
            'totalDieselInventories' => $dieselStats['total'] ?? 0,
            'todayDieselInventories' => $dieselStats['today'] ?? 0,
            'dieselFuelBalance' => $dieselStats['latest_available_fuel_liters'] ?? 0,
        ];
    }
}
