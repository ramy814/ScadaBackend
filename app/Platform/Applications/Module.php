<?php

declare(strict_types=1);

namespace App\Platform\Applications;

/**
 * What every application module contributes to the shared platform shell.
 * Adding a fourth Flutter application = one Module + one config/applications.php entry.
 */
interface Module
{
    /**
     * Contextual navigation for this application's workspace.
     *
     * @return array<int, array{label: string, items: array<int, array{key: string, label: string, route: string, icon: string, badge?: ?int}>}>
     */
    public function navigation(int $userId): array;

    /**
     * Decisions waiting for this user in this application (executive overview).
     * Only actionable items; never decorative totals.
     *
     * @return array<int, array{key: string, label: string, count: int, severity: string, url: string, hint?: ?string}>
     */
    public function attention(int $userId): array;

    /**
     * Pulse metrics for the executive overview (each must support a decision).
     *
     * @return array<int, array{key: string, label: string, value: int|float|string|null, hint?: ?string, url?: ?string}>
     */
    public function pulse(int $userId): array;
}
