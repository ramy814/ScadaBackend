<?php

declare(strict_types=1);

namespace App\Repositories\Wells;

use App\Models\Wells\WellReading;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ReadingRepository
{
    public function __construct(
        private readonly WellReading $model
    ) {
    }

    public function all(): Collection
    {
        return $this->model->with(['well', 'inspector'])->orderByDesc('READING_DATE')->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with(['well', 'inspector'])->orderByDesc('READING_DATE');

        if (!empty($filters['well_oid'])) {
            $query->where('WELL_OID', $filters['well_oid']);
        }

        if (!empty($filters['inspector_oid'])) {
            $query->where('INSPECTOR_OID', $filters['inspector_oid']);
        }

        if (!empty($filters['quality'])) {
            $query->where('QUALITY', $filters['quality']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('READING_DATE', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('READING_DATE', '<=', $filters['date_to']);
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?WellReading
    {
        return $this->model->with(['well', 'inspector', 'generatorLogs'])->find($id);
    }

    public function findOrFail(int $id): WellReading
    {
        return $this->model->with(['well', 'inspector', 'generatorLogs'])->findOrFail($id);
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
            ->with('well')
            ->orderByDesc('READING_DATE')
            ->get();
    }

    public function findByDateRange(Carbon $from, Carbon $to): Collection
    {
        return $this->model->whereBetween('READING_DATE', [$from, $to])
            ->with(['well', 'inspector'])
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

        $qualityStats = $this->model->selectRaw('QUALITY as quality, COUNT(*) as "count"')
            ->groupBy('QUALITY')
            ->pluck('count', 'quality')
            ->toArray();

        $avgFlowRate = (float) $this->model->avg('FLOW_RATE');
        $avgSalinity = (float) $this->model->avg('SALINITY');
        $avgChlorine = (float) $this->model->avg('CHLORINE');

        return [
            'total' => $total,
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'quality_distribution' => $qualityStats,
            'avg_flow_rate' => round($avgFlowRate, 2),
            'avg_salinity' => round($avgSalinity, 2),
            'avg_chlorine' => round($avgChlorine, 2),
        ];
    }

    public function getRecentReadings(int $limit = 10): Collection
    {
        return $this->model->with(['well', 'inspector'])
            ->orderByDesc('READING_DATE')
            ->limit($limit)
            ->get();
    }

    public function getTodayCount(): int
    {
        return $this->model->whereDate('READING_DATE', Carbon::today())->count();
    }

    public function getPoorQualityReadings(): Collection
    {
        return $this->model->where('QUALITY', 'poor')
            ->with(['well', 'inspector'])
            ->orderByDesc('READING_DATE')
            ->get();
    }
}
