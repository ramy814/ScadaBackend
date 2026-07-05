<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\WellReading;
use App\Models\Wells\ReadingGeneratorLog;
use App\Repositories\Wells\ReadingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingService
{
    public function __construct(
        private readonly ReadingRepository $readingRepository,
    ) {
    }

    public function getPaginatedReadings(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->readingRepository->paginate($perPage, $filters);
    }

    public function getReading(int $id): WellReading
    {
        return $this->readingRepository->findOrFail($id);
    }

    public function processReading(array $data, array $generatorLogs = []): WellReading
    {
        return DB::transaction(function () use ($data, $generatorLogs) {
            $data['SYNCED_AT'] = now();
            $reading = $this->readingRepository->create($data);

            foreach ($generatorLogs as $log) {
                ReadingGeneratorLog::create([
                    'READING_OID' => $reading->OID,
                    'GENERATOR_OID' => $log['GENERATOR_OID'],
                    'CURRENT_READING' => $log['CURRENT_READING'],
                    'GALLONS' => $log['GALLONS'],
                    'CREATED_AT' => now(),
                ]);
            }

            return $reading->fresh(['well', 'inspector', 'generatorLogs']);
        });
    }

    public function batchSync(array $readings, int $inspectorId): array
    {
        $results = [];

        foreach ($readings as $readingData) {
            $generatorLogs = $readingData['generator_logs'] ?? [];
            unset($readingData['generator_logs']);

            $readingData['INSPECTOR_OID'] = $inspectorId;

            $results[] = $this->processReading($readingData, $generatorLogs);
        }

        return $results;
    }

    public function getStatistics(): array
    {
        return $this->readingRepository->getStats();
    }

    public function getProductionReport(array $filters = []): array
    {
        $rows = $this->readingRepository->getReportReadings($filters)->map(function (WellReading $reading, int $index): array {
            $previous = $this->readingRepository->getPreviousReading($reading);
            $currentReading = (float) ($this->attribute($reading, 'FLOW_RATE') ?? 0);
            $previousReading = $previous ? (float) ($this->attribute($previous, 'FLOW_RATE') ?? 0) : null;
            $operatingHours = $previousReading === null ? 0.0 : $currentReading - $previousReading;
            $capacity = (float) ($this->attribute($reading->well, 'CAPACITY') ?? 0);

            return [
                'index' => $index + 1,
                'well_name' => $this->attribute($reading->well, 'NAME') ?? '-',
                'well_number' => $this->attribute($reading->well, 'WELL_NUM') ?? '-',
                'coordinates' => $this->coordinates($reading),
                'capacity' => $capacity,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'operating_hours' => $operatingHours,
                'production' => $operatingHours * $capacity,
                'inspector_name' => $this->attribute($reading->inspector, 'NAME') ?? '-',
                'reading_date' => $this->attribute($reading, 'READING_DATE'),
                'notes' => '',
            ];
        })->values();

        return [
            'filters' => $filters,
            'rows' => $rows,
            'total_operating_hours' => $rows->sum('operating_hours'),
            'total_production' => $rows->sum('production'),
            'generated_at' => now(),
        ];
    }

    public function getReadingsByWell(int $wellId): Collection
    {
        return $this->readingRepository->findByWell($wellId);
    }

    public function getReadingsByInspector(int $inspectorId): Collection
    {
        return $this->readingRepository->findByInspector($inspectorId);
    }

    public function getRecentReadings(int $limit = 10): Collection
    {
        return $this->readingRepository->getRecentReadings($limit);
    }

    public function getReadingsByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->readingRepository->findByDateRange($from, $to);
    }

    private function coordinates(WellReading $reading): string
    {
        $latitude = $this->attribute($reading->well, 'LATITUDE');
        $longitude = $this->attribute($reading->well, 'LONGITUDE');

        if ($latitude === null || $longitude === null) {
            return '';
        }

        return $latitude . ', ' . $longitude;
    }

    private function attribute(?object $model, string $key): mixed
    {
        if (! $model || ! method_exists($model, 'getAttribute')) {
            return null;
        }

        return $model->getAttribute($key) ?? $model->getAttribute(strtolower($key));
    }
}
