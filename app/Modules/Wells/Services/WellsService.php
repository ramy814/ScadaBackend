<?php

declare(strict_types=1);

namespace App\Modules\Wells\Services;

use App\Modules\Wells\Repositories\WellsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Presentation for the Wells workspace. Oracle PDO returns lowercase attributes, so
 * every value is read through attr() and shaped into explicit lowercase payloads.
 */
class WellsService
{
    public function __construct(private readonly WellsRepository $wells) {}

    public function today(): array
    {
        $today = CarbonImmutable::today();
        $missing = $this->wells->activeWellsWithoutReading($today);

        return [
            'stats' => [
                'active_wells' => $this->wells->activeWellCount(),
                'readings_today' => $this->wells->readingsOn($today),
                'missing_today' => $missing->count(),
                'unread_signals' => $this->wells->unreadSignalCount(),
            ],
            'missing' => $missing->map(fn (Model $w): array => [
                'oid' => (int) $w->getKey(),
                'name' => $this->attr($w, 'name'),
                'number' => $this->attr($w, 'well_num'),
                'last_reading_at' => $this->date($this->attr($w, 'last_reading_at')),
            ])->values(),
            'recent' => $this->wells->recentReadings(8)->map(fn (Model $r): array => $this->reading($r))->values(),
        ];
    }

    public function readings(array $filters): array
    {
        $page = $this->wells->paginateReadings($filters);
        $page->getCollection()->transform(fn (Model $r): array => $this->reading($r));

        return ['readings' => $page, 'filters' => $filters];
    }

    public function signals(?string $state): array
    {
        $page = $this->wells->paginateSignals($state);
        $media = $this->wells->signalMedia($page->getCollection()->map(fn (Model $s): int => (int) $s->getKey())->all());
        $page->getCollection()->transform(fn (Model $s): array => [
            'oid' => (int) $s->getKey(),
            'message' => $this->attr($s, 'message'),
            'is_read' => (bool) $this->attr($s, 'is_read'),
            'created_at' => $this->date($this->attr($s, 'created_at')),
            'well' => $this->attr($s->well, 'name'),
            'inspector' => $this->attr($s->inspector, 'name'),
            'type' => $s->notificationType ? ['name' => $this->attr($s->notificationType, 'name'), 'color' => $this->attr($s->notificationType, 'color'), 'icon' => $this->attr($s->notificationType, 'icon')] : null,
            'media' => $media->get((int) $s->getKey(), collect())->map(fn (Model $m): array => ['oid' => (int) $m->getKey(), 'url' => route('wells.activity-media.show', $m->getKey(), false)])->values(),
        ]);

        return ['signals' => $page, 'state' => $state ?? 'all', 'unread' => $this->wells->unreadSignalCount()];
    }

    public function markRead(?int $id): void
    {
        $id === null ? $this->wells->markAllSignalsRead() : $this->wells->markSignalRead($id);
    }

    /** Same private-disk streaming as the legacy ActivityMediaService::response(). */
    public function media(int $id): StreamedResponse
    {
        $media = $this->wells->findMedia($id);
        $path = (string) $this->attr($media, 'file_path');
        abort_unless(Storage::disk('legacy_media')->exists($path), 404);

        return Storage::disk('legacy_media')->response($path, (string) $this->attr($media, 'original_name'), [
            'Content-Type' => (string) $this->attr($media, 'mime_type'),
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function reading(Model $r): array
    {
        return [
            'oid' => (int) $r->getKey(),
            'well' => $this->attr($r->well, 'name'),
            'well_number' => $this->attr($r->well, 'well_num'),
            'inspector' => $this->attr($r->inspector, 'name'),
            'generator_hours' => $this->attr($r, 'generator_hours_reading'),
            'pump_hours' => $this->attr($r, 'pump_hours_reading'),
            'chlorine' => $this->attr($r, 'chlorine'),
            'salinity' => $this->attr($r, 'salinity'),
            'quality' => $this->attr($r, 'quality'),
            'reading_date' => $this->date($this->attr($r, 'reading_date')),
            'created_at' => $this->date($this->attr($r, 'created_at')),
        ];
    }

    private function attr(?Model $model, string $key): mixed
    {
        return $model?->getAttribute($key) ?? $model?->getAttribute(strtoupper($key));
    }

    /** OCI8 may return dates as strings even with casts (legacy note). */
    private function date(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : CarbonImmutable::parse($value)->toIso8601String();
    }
}
