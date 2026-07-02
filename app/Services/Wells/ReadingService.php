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

    public function getPoorQualityAlerts(): Collection
    {
        return $this->readingRepository->getPoorQualityReadings();
    }

    public function getReadingsByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->readingRepository->findByDateRange($from, $to);
    }
}
