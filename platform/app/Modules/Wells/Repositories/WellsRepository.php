<?php

declare(strict_types=1);

namespace App\Modules\Wells\Repositories;

use App\Modules\Wells\Models\Well;
use App\Modules\Wells\Models\WellActivityMedia;
use App\Modules\Wells\Models\WellNotification;
use App\Modules\Wells\Models\WellReading;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Wells queries for the platform. Rules kept from the legacy module: READING_DATE is
 * the field date for chronology and "today"; CREATED_AT orders "latest submitted";
 * soft-deleted readings are excluded (model scope + explicit DELETED_AT IS NULL in
 * raw subqueries).
 */
class WellsRepository
{
    public function activeWellCount(): int
    {
        return Well::query()->where('IS_ACTIVE', 1)->count();
    }

    public function readingsOn(CarbonImmutable $day): int
    {
        return WellReading::query()->whereBetween('READING_DATE', [$day->startOfDay(), $day->endOfDay()])->count();
    }

    /** Active wells with no non-deleted reading whose READING_DATE is today. */
    public function activeWellsWithoutReading(CarbonImmutable $day): Collection
    {
        return Well::query()
            ->where('IS_ACTIVE', 1)
            ->whereNotExists(fn (QueryBuilder $q) => $q->selectRaw('1')->from('well_readings as r')
                ->whereColumn('r.WELL_OID', 'wells.OID')
                ->whereNull('r.DELETED_AT')
                ->whereBetween('r.READING_DATE', [$day->startOfDay(), $day->endOfDay()]))
            ->addSelect(['last_reading_at' => WellReading::query()->selectRaw('MAX(READING_DATE)')->whereColumn('WELL_OID', 'wells.OID')])
            ->orderBy('NAME')
            ->get();
    }

    public function recentReadings(int $limit): Collection
    {
        return WellReading::query()->with(['well:oid,name,well_num', 'inspector:oid,name'])
            ->orderByDesc('CREATED_AT')->orderByDesc('OID')->limit($limit)->get();
    }

    public function paginateReadings(array $filters): LengthAwarePaginator
    {
        return WellReading::query()->with(['well:oid,name,well_num', 'inspector:oid,name'])
            ->when(filled($filters['search'] ?? null), fn (Builder $q) => $q->whereHas('well', fn (Builder $w) => $w
                ->where('NAME', 'like', '%'.$filters['search'].'%')->orWhere('WELL_NUM', 'like', '%'.$filters['search'].'%')))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $q) => $q->where('READING_DATE', '>=', CarbonImmutable::parse($filters['date_from'])->startOfDay()))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $q) => $q->where('READING_DATE', '<=', CarbonImmutable::parse($filters['date_to'])->endOfDay()))
            ->orderByDesc('CREATED_AT')->orderByDesc('OID')
            ->paginate(20)->withQueryString();
    }

    public function unreadSignalCount(): int
    {
        return WellNotification::query()->where('IS_READ', false)->count();
    }

    public function paginateSignals(?string $state): LengthAwarePaginator
    {
        return WellNotification::query()->with(['well:oid,name,well_num', 'notificationType', 'inspector:oid,name'])
            ->when($state === 'unread', fn (Builder $q) => $q->where('IS_READ', false))
            ->orderByDesc('CREATED_AT')
            ->paginate(20)->withQueryString();
    }

    /** @param array<int, int> $signalIds @return Collection<int, Collection<int, WellActivityMedia>> */
    public function signalMedia(array $signalIds): Collection
    {
        return WellActivityMedia::query()->where('ACTIVITY_TYPE', 'notification')
            ->whereIn('ACTIVITY_OID', $signalIds ?: [0])->get()
            ->groupBy(fn (WellActivityMedia $m): int => (int) $m->activity_oid);
    }

    public function markSignalRead(int $id): void
    {
        WellNotification::query()->whereKey($id)->update(['IS_READ' => true, 'READ_AT' => now()]);
    }

    public function markAllSignalsRead(): int
    {
        return WellNotification::query()->where('IS_READ', false)->update(['IS_READ' => true, 'READ_AT' => now()]);
    }

    public function findMedia(int $id): WellActivityMedia
    {
        return WellActivityMedia::query()->findOrFail($id);
    }
}
