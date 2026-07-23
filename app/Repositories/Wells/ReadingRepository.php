<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\WellReading;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReadingRepository
{
    public function __construct(
        private readonly WellReading $model
    ) {}

    public function all(): Collection
    {
        return $this->model->with($this->relationsWithWellSourceCode())->orderByDesc('READING_DATE')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model
            ->with($this->relationsWithWellSourceCode())
            ->orderByDesc('CREATED_AT')
            ->orderByDesc('OID');

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getReportReadings(array $filters = [], int $limit = 5000): Collection
    {
        $table = $this->model->getTable();
        $query = $this->model
            ->with($this->relationsWithWellSourceCode())
            ->whereNotExists(function (QueryBuilder $newerReading) use ($filters, $table): void {
                $newerReading
                    ->selectRaw('1')
                    ->from($table . ' as newer_reading')
                    ->whereColumn('newer_reading.WELL_OID', $table . '.WELL_OID')
                    ->where(function (QueryBuilder $chronology) use ($table): void {
                        $chronology
                            ->whereColumn('newer_reading.READING_DATE', '>', $table . '.READING_DATE')
                            ->orWhere(function (QueryBuilder $sameDate) use ($table): void {
                                $sameDate
                                    ->whereColumn('newer_reading.READING_DATE', $table . '.READING_DATE')
                                    ->whereColumn('newer_reading.OID', '>', $table . '.OID');
                            })
                            ->orWhere(function (QueryBuilder $missingCurrentDate) use ($table): void {
                                $missingCurrentDate
                                    ->whereNull($table . '.READING_DATE')
                                    ->where(function (QueryBuilder $datedOrNewerUndated) use ($table): void {
                                        $datedOrNewerUndated
                                            ->whereNotNull('newer_reading.READING_DATE')
                                            ->orWhere(function (QueryBuilder $newerUndated) use ($table): void {
                                                $newerUndated
                                                    ->whereNull('newer_reading.READING_DATE')
                                                    ->whereColumn('newer_reading.OID', '>', $table . '.OID');
                                            });
                                    });
                            });
                    });

                $this->applyFilters($newerReading, $filters, 'newer_reading.');
            })
            ->orderBy('WELL_OID')
            ->orderByDesc('READING_DATE')
            ->orderByDesc('OID');

        $this->applyFilters($query, $filters);

        return $query->limit($limit)->get();
    }

    public function getPreviousReading(WellReading $reading): ?WellReading
    {
        $wellId = $reading->getAttribute('WELL_OID') ?? $reading->getAttribute('well_oid');
        $readingDate = $reading->getAttribute('READING_DATE') ?? $reading->getAttribute('reading_date');
        $readingId = $reading->getAttribute('OID') ?? $reading->getAttribute('oid');

        if ($wellId === null || $readingDate === null || $readingId === null) {
            return null;
        }

        return $this->model
            ->where('WELL_OID', $wellId)
            ->where(function ($query) use ($reading): void {
                $readingDate = $reading->getAttribute('READING_DATE') ?? $reading->getAttribute('reading_date');
                $readingId = $reading->getAttribute('OID') ?? $reading->getAttribute('oid');

                $query
                    ->where('READING_DATE', '<', $readingDate)
                    ->orWhere(function ($sameTimeQuery) use ($readingDate, $readingId): void {
                        $sameTimeQuery
                            ->where('READING_DATE', $readingDate)
                            ->where('OID', '<', $readingId);
                    });
            })
            ->orderByDesc('READING_DATE')
            ->orderByDesc('OID')
            ->first();
    }

    private function applyFilters($query, array $filters, string $columnPrefix = ''): void
    {
        if (! empty($filters['well_oid'])) {
            $query->where($columnPrefix . 'WELL_OID', $filters['well_oid']);
        }

        if (! empty($filters['inspector_oid'])) {
            $query->where($columnPrefix . 'INSPECTOR_OID', $filters['inspector_oid']);
        }

        if (! empty($filters['quality'])) {
            $query->where($columnPrefix . 'QUALITY', $filters['quality']);
        }

        if (! empty($filters['date_from'])) {
            $query->where(
                $columnPrefix . 'READING_DATE',
                '>=',
                Carbon::parse($filters['date_from'])->startOfDay(),
            );
        }

        if (! empty($filters['date_to'])) {
            $query->where(
                $columnPrefix . 'READING_DATE',
                '<=',
                Carbon::parse($filters['date_to'])->endOfDay(),
            );
        }
    }

    public function find(int $id): ?WellReading
    {
        return $this->model->with($this->relationsWithWellSourceCode(['generatorLogs']))->find($id);
    }

    public function findOrFail(int $id): WellReading
    {
        return $this->model->with($this->relationsWithWellSourceCode(['generatorLogs']))->findOrFail($id);
    }

    public function create(array $data): WellReading
    {
        return $this->model->create($data);
    }

    public function findByWell(int $wellId): Collection
    {
        return $this->model->where('WELL_OID', $wellId)
            ->orderByDesc('READING_DATE')
            ->get();
    }

    public function findByInspector(int $inspectorId): Collection
    {
        return $this->model->where('INSPECTOR_OID', $inspectorId)
            ->with($this->relationsWithWellSourceCode())
            ->orderByDesc('READING_DATE')
            ->get();
    }

    public function findByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->model->whereBetween('READING_DATE', [$from, $to])
            ->with($this->relationsWithWellSourceCode())
            ->orderByDesc('READING_DATE')
            ->get();
    }

    public function getStats(): array
    {
        $total = $this->model->count();
        $today = $this->model->whereDate('READING_DATE', Carbon::today())->count();
        $thisWeek = $this->model->whereBetween('READING_DATE', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ])->count();
        $thisMonth = $this->model->whereMonth('READING_DATE', Carbon::now()->month)
            ->whereYear('READING_DATE', Carbon::now()->year)
            ->count();

        $avgFlowRate = (float) $this->model->avg('FLOW_RATE');
        $avgSalinity = (float) $this->model->avg('SALINITY');
        $avgChlorine = (float) $this->model->avg('CHLORINE');

        return [
            'total' => $total,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'avg_flow_rate' => round($avgFlowRate, 2),
            'avg_salinity' => round($avgSalinity, 2),
            'avg_chlorine' => round($avgChlorine, 2),
        ];
    }

    public function getRecentReadings(int $limit = 10): Collection
    {
        return $this->model->with($this->relationsWithWellSourceCode())
            ->orderByDesc('READING_DATE')
            ->limit($limit)
            ->get();
    }

    public function getRecentlySubmittedReadings(int $limit = 10): Collection
    {
        return $this->model->with($this->relationsWithWellSourceCode())
            ->orderByDesc('CREATED_AT')
            ->orderByDesc('OID')
            ->limit($limit)
            ->get();
    }

    public function getTodayCount(): int
    {
        return $this->model->whereDate('READING_DATE', Carbon::today())->count();
    }

    private function relationsWithWellSourceCode(array $extra = []): array
    {
        return array_merge([
            'well' => function ($query): void {
                $query
                    ->select('wells.*', 'source_wells.SOURCE_WELL_CODE')
                    ->leftJoin(
                        DB::raw('DSS_CORE.WTR_WELLS source_wells'),
                        'source_wells.WELL_ID',
                        '=',
                        'wells.WELL_NUM'
                    );
            },
            'inspector',
            'media',
        ], $extra);
    }
}
