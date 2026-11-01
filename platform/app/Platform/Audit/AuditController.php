<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __invoke(AuditFilterRequest $request, AuditLogRepository $audit): Response
    {
        $filters = $request->validated();
        $page = $audit->paginate($filters);
        $page->getCollection()->transform(fn (AuditLog $log): array => [
            'id' => $log->id,
            'event' => $log->event_type,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'actor' => $log->payload['actor_name'] ?? $log->actor,
            'payload' => $log->payload,
            'at' => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('Platform/Audit', ['logs' => $page, 'filters' => $filters, 'events' => $audit->eventTypes(), 'tripBase' => url('/fleet/trips')]);
    }
}
