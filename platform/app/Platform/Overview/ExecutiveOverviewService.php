<?php

declare(strict_types=1);

namespace App\Platform\Overview;

use App\Platform\Access\AccessService;
use App\Platform\Applications\ApplicationRegistry;
use App\Platform\Operations\OperationalStateService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "What needs my decision now?" across every application the viewer may access.
 * Each module answers for itself; one failing data source degrades only its card.
 */
class ExecutiveOverviewService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly ApplicationRegistry $registry,
        private readonly OperationalStateService $operations,
    ) {}

    public function forUser(int $userId, string $locale): array
    {
        $applications = array_map(function (string $key) use ($userId, $locale): array {
            $app = $this->registry->present($key, $locale);
            try {
                $module = $this->registry->module($key);

                return [...$app, 'available' => true, 'attention' => $module->attention($userId), 'pulse' => $module->pulse($userId)];
            } catch (Throwable $exception) {
                Log::warning('Overview module unavailable.', ['application' => $key, 'exception' => $exception->getMessage()]);

                return [...$app, 'available' => false, 'attention' => [], 'pulse' => []];
            }
        }, $this->access->applications($userId));

        return [
            'applications' => $applications,
            'operations' => $this->operations->current(),
            'generatedAt' => now()->toIso8601String(),
        ];
    }
}
