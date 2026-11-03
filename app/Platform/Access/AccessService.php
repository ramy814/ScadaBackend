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

    /** @var array<int, array{create: bool, enter: bool, approve: bool}> */
    private array $assistant = [];

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
        // Locally only the configured dev identity manages access, so other demo users keep their real scope.
        return in_array($userId, config('platform.permissions_admin_user_ids', []), true)
            || (app()->environment('local') && $userId === (int) config('platform.local_admin_user_id'));
    }

    public function hasAnyAccess(int $userId): bool
    {
        return $this->applications($userId) !== [] || $this->isPlatformAdmin($userId) || $this->assistant($userId)['create'];
    }

    /**
     * Manual-trip assistant capabilities, derived live from Oracle station scope
     * (VEHICLE_STAGES.ACCESS_TYPE 1 entry, 2 approval, 3 both). They never grant
     * the Fleet dashboard itself (legacy rule).
     *
     * @return array{create: bool, enter: bool, approve: bool}
     */
    public function assistant(int $userId): array
    {
        return $this->assistant[$userId] ??= [
            'create' => $this->permissions->hasActionPermission($userId, SystemPermissionRepository::CREATE_MANUAL_TRIP),
            'enter' => $this->permissions->hasActionPermission($userId, SystemPermissionRepository::ENTER_MANUAL_TRIP),
            'approve' => $this->permissions->hasActionPermission($userId, SystemPermissionRepository::APPROVE_MANUAL_TRIP),
        ];
    }

    /** Workspaces shown in the shell: granted applications plus Fleet for assistants. */
    public function workspaces(int $userId): array
    {
        $keys = $this->applications($userId);
        if (! in_array('fleet', $keys, true) && $this->assistant($userId)['create']) {
            array_unshift($keys, 'fleet');
        }

        return $keys;
    }

    /** Landing page for an assistant without any granted application. */
    public function assistantHome(int $userId): string
    {
        $assistant = $this->assistant($userId);

        return $assistant['enter'] || ! $assistant['approve'] ? route('fleet.manual') : route('fleet.manual.requests');
    }

    public function can(int $userId, string $action): bool
    {
        return $this->permissions->hasActionPermission($userId, $action);
    }
}
