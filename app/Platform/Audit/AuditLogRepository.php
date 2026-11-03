<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Read-only view of the append-only AUDIT_LOGS table (never updated or deleted). */
class AuditLogRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->when(filled($filters['event'] ?? null), fn (Builder $q) => $q->where('event_type', $filters['event']))
            ->when(filled($filters['entity'] ?? null), fn (Builder $q) => $q->where('entity_id', (int) ltrim((string) $filters['entity'], '#')))
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();
    }

    /** @return array<int, string> */
    public function eventTypes(): array
    {
        return AuditLog::query()->distinct()->orderBy('event_type')->pluck('event_type')->all();
    }
}
