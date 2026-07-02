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
        
        $qualityDist = $readingStats['quality_distribution'];
        if (empty($qualityDist)) {
            $qualityDist = ['excellent' => 0, 'good' => 0, 'acceptable' => 0, 'poor' => 0];
        } else {
            $qualityDist = array_merge(['excellent' => 0, 'good' => 0, 'acceptable' => 0, 'poor' => 0], $qualityDist);
        }

        $recentReadings = $this->readingRepository->getRecentReadings(5)->map(function ($reading) {
            return [
                'oid' => $reading->OID ?? $reading->id,
                'well_name' => $reading->well->WELL_NAME ?? 'N/A',
                'inspector_name' => $reading->inspector->NAME ?? 'N/A',
                'flow_rate' => $reading->FLOW_RATE,
                'chlorine' => $reading->CHLORINE,
                'salinity' => $reading->SALINITY,
                'quality' => $reading->QUALITY,
                'reading_date' => $reading->READING_DATE,
            ];
        })->toArray();

        $dieselStats = $this->dieselInventoryRepository->getStats();
        $recentDieselInventories = $this->dieselInventoryRepository->getRecentInventories(5)->map(function ($inventory) {
            return [
                'oid' => $inventory->OID ?? $inventory->id,
                'well_name' => $inventory->well->NAME ?? 'N/A',
                'inspector_name' => $inventory->inspector->NAME ?? 'N/A',
                'generator_oid' => $inventory->GENERATOR_OID,
                'generator_hours_reading' => $inventory->GENERATOR_HOURS_READING,
                'diesel_height_cm' => $inventory->DIESEL_HEIGHT_CM,
                'available_fuel_liters' => $inventory->AVAILABLE_FUEL_LITERS,
                'inventory_date' => $inventory->INVENTORY_DATE,
            ];
        })->toArray();

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
            'recentReadings' => $recentReadings,
            'recentDieselInventories' => $recentDieselInventories,
            'qualityDistribution' => $qualityDist,
            'poorQualityAlerts' => $this->readingRepository->getPoorQualityReadings()->take(5),
        ];
    }
}
