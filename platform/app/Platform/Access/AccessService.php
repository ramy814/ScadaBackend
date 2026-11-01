<?php

declare(strict_types=1);

namespace App\Platform\Access;

use App\Platform\Applications\ApplicationRegistry;

/**
 * The single question "what may this SSO user see?" for the shell, middleware and
 * the overview. Delegates to the ported SystemPermissionRepository so semantics
 * (fail open for system entry, fail closed for actions) match the legacy backend.
 * Memoized per request because the shell asks several times.
 */
class AccessService
{
    /** @var array<int, array<int, string>> */
    private array $applications = [];

    public function __construct(
        private readonly SystemPermissionRepository $permissions,
        private readonly ApplicationRegistry $registry,
    ) {}

    /** @return array<int, string> application keys in registry order */
    public function applications(int $userId): array
    {
        if (! isset($this->applications[$userId])) {
            $granted = $this->permissions->accessibleSystems($userId)->all();
            $this->applications[$userId] = array_values(array_filter(
                $this->registry->keys(),
                fn (string $key): bool => in_array($key, $granted, true),
            ));
        }

        return $this->applications[$userId];
    }

    public function canAccess(int $userId, string $application): bool
    {
        return in_array($application, $this->applications($userId), true);
    }

    /** Access and operations management (legacy MOG_PERMISSIONS_ADMIN_USER_IDS rule). */
    public function isPlatformAdmin(int $userId): bool
    {
        return app()->environment('local')
            || in_array($userId, config('platform.permissions_admin_user_ids', []), true);
    }

    public function hasAnyAccess(int $userId): bool
    {
        return $this->applications($userId) !== [] || $this->isPlatformAdmin($userId);
    }

    public function can(int $userId, string $action): bool
    {
        return $this->permissions->hasActionPermission($userId, $action);
    }
}
