<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SystemOperationalState;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SystemOperationalStateRepository
{
    public const GLOBAL_SCOPE = 'global';

    private const CACHE_KEY = 'system_operational_state.global.v1';

    private const CACHE_SECONDS = 5;

    public function __construct(private readonly OracleReadiness $readiness) {}

    /**
     * @return array{
     *     shutdown_enabled: bool,
     *     reason: ?string,
     *     changed_by_user_id: ?int,
     *     changed_by_name: ?string,
     *     changed_at: ?string
     * }
     */
    public function current(): array
    {
        try {
            return $this->cache()->remember(
                self::CACHE_KEY,
                self::CACHE_SECONDS,
                fn (): array => $this->readCurrent(),
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to use the operational-state cache; reading Oracle directly.', [
                'exception' => $exception,
            ]);

            return $this->readCurrent();
        }
    }

    /**
     * @return array{
     *     shutdown_enabled: bool,
     *     reason: ?string,
     *     changed_by_user_id: ?int,
     *     changed_by_name: ?string,
     *     changed_at: ?string
     * }
     */
    private function readCurrent(): array
    {
        if (! $this->readiness->available()) {
            return $this->operationalDefault();
        }

        try {
            $state = SystemOperationalState::query()
                ->where('scope', self::GLOBAL_SCOPE)
                ->first();
        } catch (Throwable $exception) {
            if ($this->isTableMissing($exception)) {
                Log::warning('SYSTEM_OPERATIONAL_STATES is missing; systems remain operational.');
            } else {
                Log::error('Unable to read system operational state; systems remain operational.', [
                    'exception' => $exception,
                ]);
            }

            return $this->operationalDefault();
        }

        if (! $state) {
            return $this->operationalDefault();
        }

        return $this->format($state);
    }

    /**
     * @return array{
     *     shutdown_enabled: bool,
     *     reason: ?string,
     *     changed_by_user_id: ?int,
     *     changed_by_name: ?string,
     *     changed_at: ?string
     * }
     */
    public function update(
        bool $shutdownEnabled,
        ?string $reason,
        int $actorUserId,
        string $actorName,
    ): array {
        if (! $this->readiness->available()) {
            throw new RuntimeException('تعذر الوصول إلى قاعدة البيانات لتحديث حالة تشغيل الأنظمة.');
        }

        try {
            $state = SystemOperationalState::query()->updateOrCreate(
                ['scope' => self::GLOBAL_SCOPE],
                [
                    'is_suspended' => $shutdownEnabled,
                    'reason' => $shutdownEnabled ? $reason : null,
                    'changed_by_user_id' => $actorUserId,
                    'changed_by_name' => $actorName,
                    'changed_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            if ($this->isTableMissing($exception)) {
                throw new RuntimeException(
                    'جدول التحكم بتشغيل الأنظمة غير موجود. شغّل ترحيل قاعدة البيانات أولاً.',
                    previous: $exception,
                );
            }

            Log::error('Unable to update system operational state.', [
                'exception' => $exception,
            ]);

            throw new RuntimeException(
                'تعذر تحديث حالة تشغيل الأنظمة. تحقق من اتصال قاعدة البيانات ثم أعد المحاولة.',
                previous: $exception,
            );
        }

        $formatted = $this->format($state);

        try {
            $this->cache()->put(self::CACHE_KEY, $formatted, self::CACHE_SECONDS);
        } catch (Throwable $exception) {
            Log::warning('Operational state changed but its cache could not be refreshed.', [
                'exception' => $exception,
            ]);
        }

        return $formatted;
    }

    /**
     * @return array{
     *     shutdown_enabled: false,
     *     reason: null,
     *     changed_by_user_id: null,
     *     changed_by_name: null,
     *     changed_at: null
     * }
     */
    private function operationalDefault(): array
    {
        return [
            'shutdown_enabled' => false,
            'reason' => null,
            'changed_by_user_id' => null,
            'changed_by_name' => null,
            'changed_at' => null,
        ];
    }

    /**
     * @return array{
     *     shutdown_enabled: bool,
     *     reason: ?string,
     *     changed_by_user_id: ?int,
     *     changed_by_name: ?string,
     *     changed_at: ?string
     * }
     */
    private function format(SystemOperationalState $state): array
    {
        return [
            'shutdown_enabled' => (bool) $state->is_suspended,
            'reason' => filled($state->reason) ? (string) $state->reason : null,
            'changed_by_user_id' => $state->changed_by_user_id === null
                ? null
                : (int) $state->changed_by_user_id,
            'changed_by_name' => filled($state->changed_by_name)
                ? (string) $state->changed_by_name
                : null,
            'changed_at' => optional($state->changed_at)?->toIso8601String(),
        ];
    }

    private function isTableMissing(Throwable $exception): bool
    {
        return (int) $exception->getCode() === 942
            || str_contains($exception->getMessage(), 'ORA-00942');
    }

    private function cache(): CacheRepository
    {
        return Cache::store(app()->environment('testing') ? 'array' : 'file');
    }
}
