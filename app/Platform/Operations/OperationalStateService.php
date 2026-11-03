<?php

declare(strict_types=1);

namespace App\Platform\Operations;

use Illuminate\Support\Facades\Log;

class OperationalStateService
{
    public function __construct(
        private readonly SystemOperationalStateRepository $states,
    ) {}

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
        return $this->states->current();
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
        $state = $this->states->update(
            $shutdownEnabled,
            $shutdownEnabled ? trim((string) $reason) : null,
            $actorUserId,
            $actorName,
        );

        Log::notice('System operational state changed.', [
            'shutdown_enabled' => $shutdownEnabled,
            'reason' => $state['reason'],
            'actor_user_id' => $actorUserId,
            'actor_name' => $actorName,
        ]);

        return $state;
    }
}
