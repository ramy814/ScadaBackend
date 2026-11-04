<?php

declare(strict_types=1);

namespace App\Services\Wells;

use App\Models\Wells\ReadingGeneratorLog;
use App\Models\Wells\WellReading;
use App\Repositories\Wells\ReadingRepository;
use App\Repositories\Wells\WellRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingService
{
    private const MAX_NORMAL_OPERATING_HOURS = 20.0;

    private const REPORT_COLUMNS = [
        ['key' => 'status', 'label' => 'المؤشر', 'type' => 'status', 'default' => true],
        ['key' => 'well_name', 'label' => 'اسم البئر', 'type' => 'text', 'default' => true],
        ['key' => 'well_number', 'label' => 'الرقم/الاسم الرسمي', 'type' => 'text', 'default' => true],
        ['key' => 'coordinates', 'label' => 'الإحداثيات', 'type' => 'text', 'default' => false],
        ['key' => 'capacity', 'label' => 'السعة (م³/ساعة)', 'type' => 'number', 'default' => true],
        ['key' => 'previous_reading', 'label' => 'قراءة المولد السابقة', 'type' => 'number', 'default' => true],
        ['key' => 'current_reading', 'label' => 'قراءة المولد الحالية', 'type' => 'number', 'default' => true],
        ['key' => 'operating_hours', 'label' => 'فرق التشغيل (ساعة)', 'type' => 'number', 'default' => true],
        ['key' => 'production', 'label' => 'الإنتاج (م³)', 'type' => 'number', 'default' => true],
        ['key' => 'inspector_name', 'label' => 'المفتش', 'type' => 'text', 'default' => true],
        ['key' => 'reading_date', 'label' => 'تاريخ القراءة', 'type' => 'date', 'default' => true],
        ['key' => 'notes', 'label' => 'ملاحظات', 'type' => 'text', 'default' => false],
    ];

    public function __construct(
        private readonly ReadingRepository $readingRepository,
        private readonly WellRepository $wellRepository,
    ) {}

    public function getPaginatedReadings(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->readingRepository->paginate($perPage, $filters);
    }

    public function getReading(int $id): WellReading
    {
        return $this->readingRepository->findOrFail($id);
    }

    public function deleteReading(
        int $id,
        int $actorId,
        string $actorName,
        string $reason,
    ): void {
        $this->readingRepository->softDelete(
            $id,
            $actorId,
            $actorName,
            trim($reason),
        );
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
        $readings = $this->readingRepository->getReportReadings($filters)
            ->keyBy(fn (WellReading $reading): int => (int) $this->attribute($reading, 'WELL_OID'));
        $wells = $this->wellRepository->getActiveWells();

        if (! empty($filters['well_oid'])) {
            $wells = $wells
                ->filter(fn ($well): bool => (int) $well->getKey() === (int) $filters['well_oid'])
                ->values();
        }

        $rows = $wells->map(function ($well, int $index) use ($readings): array {
            $reading = $readings->get((int) $well->getKey());
            $previous = $reading ? $this->readingRepository->getPreviousReading($reading) : null;
            $currentReading = $reading ? $this->numeric(
                $this->attribute($reading, 'GENERATOR_HOURS_READING')
            ) : null;
            $previousReading = $previous ? $this->numeric(
                $this->attribute($previous, 'GENERATOR_HOURS_READING')
            ) : null;
            $operatingHours = $currentReading !== null
                && $previousReading !== null
                && $currentReading >= $previousReading
                    ? round($currentReading - $previousReading, 2)
                    : null;
            $capacity = $this->numeric($this->attribute($well, 'CAPACITY')) ?? 0.0;
            $status = $this->readingStatus(
                $reading,
                $currentReading,
                $previousReading,
                $operatingHours,
            );

            return [
                'index' => $index + 1,
                'well_oid' => (int) $well->getKey(),
                'well_name' => $this->attribute($well, 'NAME') ?? '-',
                'well_number' => $this->attribute($well, 'SOURCE_WELL_CODE')
                    ?? $this->attribute($well, 'WELL_NUM')
                    ?? '-',
                'coordinates' => $this->coordinates($well),
                'capacity' => $capacity,
                'previous_reading' => $previousReading,
                'current_reading' => $currentReading,
                'operating_hours' => $operatingHours,
                'production' => $operatingHours === null ? null : round($operatingHours * $capacity, 2),
                'inspector_name' => $reading
                    ? ($this->attribute($reading->inspector, 'NAME') ?? '-')
                    : '-',
                'reading_date' => $reading ? $this->attribute($reading, 'READING_DATE') : null,
                'has_reading' => $reading !== null,
                'status' => $status,
                'notes' => $status['key'] === 'normal' ? '' : $status['reason'],
            ];
        })->values();

        $reportedWells = $rows->where('has_reading', true)->count();
        $totalActiveWells = $rows->count();

        return [
            'filters' => $filters,
            'rows' => $rows,
            'total_operating_hours' => $rows->sum('operating_hours'),
            'total_production' => $rows->sum('production'),
            'totals' => [
                'operating_hours' => $rows->sum('operating_hours'),
                'production' => $rows->sum('production'),
            ],
            'summary' => [
                'total_active_wells' => $totalActiveWells,
                'reported_wells' => $reportedWells,
                'missing_wells' => $totalActiveWells - $reportedWells,
                'normal_wells' => $rows->where('status.key', 'normal')->count(),
                'first_reading_wells' => $rows->where('status.key', 'first')->count(),
                'review_wells' => $rows
                    ->whereIn('status.key', ['review', 'abnormal'])
                    ->count(),
                'completion_percentage' => $totalActiveWells > 0
                    ? round(($reportedWells / $totalActiveWells) * 100, 1)
                    : 0.0,
            ],
            'generated_at' => now(),
        ];
    }

    public function getReportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    public function getExportReport(array $filters = [], array $requestedColumns = []): array
    {
        return array_merge($this->getProductionReport($filters), [
            'columns' => $this->selectedColumns($requestedColumns),
        ]);
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

    private function coordinates(object $well): string
    {
        $latitude = $this->attribute($well, 'LATITUDE');
        $longitude = $this->attribute($well, 'LONGITUDE');

        if ($latitude === null || $longitude === null) {
            return '';
        }

        return $latitude.', '.$longitude;
    }

    private function readingStatus(
        ?WellReading $reading,
        ?float $currentReading,
        ?float $previousReading,
        ?float $operatingHours,
    ): array {
        if (! $reading) {
            return [
                'key' => 'missing',
                'label' => 'غير مدخلة',
                'level' => 'elevated',
                'reason' => 'لا توجد قراءة مطابقة للفترة والفلاتر المحددة',
            ];
        }

        if ($currentReading === null) {
            return [
                'key' => 'review',
                'label' => 'تحتاج مراجعة',
                'level' => 'elevated',
                'reason' => 'قراءة العداد الحالية غير متوفرة',
            ];
        }

        if ($currentReading < 0 || ($previousReading !== null && $previousReading < 0)) {
            return [
                'key' => 'abnormal',
                'label' => 'غير طبيعي',
                'level' => 'elevated',
                'reason' => 'إحدى قراءات العداد سالبة',
            ];
        }

        if ($previousReading === null) {
            return [
                'key' => 'first',
                'label' => 'قراءة أولى',
                'level' => 'info',
                'reason' => 'لا توجد قراءة سابقة للمقارنة',
            ];
        }

        if ($currentReading < $previousReading) {
            return [
                'key' => 'abnormal',
                'label' => 'غير طبيعي',
                'level' => 'elevated',
                'reason' => 'فرق التشغيل سالب لأن القراءة الحالية أقل من السابقة',
            ];
        }

        if ($operatingHours !== null && $operatingHours > self::MAX_NORMAL_OPERATING_HOURS) {
            return [
                'key' => 'abnormal',
                'label' => 'غير طبيعي',
                'level' => 'elevated',
                'reason' => 'فرق التشغيل أكبر من 20 ساعة',
            ];
        }

        return [
            'key' => 'normal',
            'label' => 'طبيعي',
            'level' => 'normal',
            'reason' => 'القراءة الحالية متسقة مع القراءة السابقة',
        ];
    }

    private function numeric(mixed $value): ?float
    {
        return $value === null || $value === '' || ! is_numeric($value) ? null : (float) $value;
    }

    private function selectedColumns(array $requested): array
    {
        $available = collect(self::REPORT_COLUMNS);
        $allowedKeys = $available->pluck('key');
        $requested = collect($requested)
            ->filter(fn (mixed $key): bool => is_string($key) && $allowedKeys->contains($key))
            ->unique()
            ->values();

        if ($requested->isEmpty()) {
            return $available->where('default', true)->values()->all();
        }

        return $requested
            ->map(fn (string $key): array => $available->firstWhere('key', $key))
            ->filter()
            ->values()
            ->all();
    }

    private function attribute(?object $model, string $key): mixed
    {
        if (! $model || ! method_exists($model, 'getAttribute')) {
            return null;
        }

        return $model->getAttribute($key) ?? $model->getAttribute(strtolower($key));
    }
}
