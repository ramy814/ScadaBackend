<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\WellReading;
use App\Repositories\Wells\DieselInventoryRepository;
use App\Repositories\Wells\InspectorRepository;
use App\Repositories\Wells\ReadingRepository;
use App\Repositories\Wells\TeamRepository;
use App\Repositories\Wells\WellRepository;
use Illuminate\Database\Eloquent\Model;

class DashboardService
{
    public function __construct(
        private readonly WellRepository $wellRepository,
        private readonly TeamRepository $teamRepository,
        private readonly InspectorRepository $inspectorRepository,
        private readonly ReadingRepository $readingRepository,
        private readonly DieselInventoryRepository $dieselInventoryRepository,
    ) {}

    public function getStats(): array
    {
        $readingStats = $this->readingRepository->getStats();
        $dieselStats = $this->dieselInventoryRepository->getStats();
        $recentReadings = $this->readingRepository
            ->getRecentlySubmittedReadings(5)
            ->map(fn (WellReading $reading): array => [
                'oid' => $reading->getKey(),
                'well_name' => $this->modelAttribute($reading->well, 'NAME') ?? 'N/A',
                'inspector_name' => $this->modelAttribute($reading->inspector, 'NAME') ?? 'N/A',
                'generator_hours_reading' => $this->modelAttribute($reading, 'GENERATOR_HOURS_READING')
                    ?? $this->modelAttribute($reading, 'FLOW_RATE'),
                'pump_hours_reading' => $this->modelAttribute($reading, 'PUMP_HOURS_READING'),
                'reading_date' => $this->modelAttribute($reading, 'READING_DATE'),
                'created_at' => $this->modelAttribute($reading, 'CREATED_AT'),
                'media' => $reading->media
                    ->map(fn ($media): array => [
                        'oid' => $media->getKey(),
                        'url' => $media->getAttribute('url'),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

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
        ];
    }

    private function modelAttribute(?Model $model, string $attribute): mixed
    {
        if ($model === null) {
            return null;
        }

        return $model->getAttribute(strtolower($attribute))
            ?? $model->getAttribute(strtoupper($attribute));
    }
}
