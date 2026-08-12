<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\MogSsoUser;
use App\Models\SystemPermission;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manages the CONTROL_UNIT.SYSTEM_PERMISSIONS table in Oracle.
 * Controls which systems (fleet, wells, valves) an SSO user may access.
 *
 * System entry checks fail open during setup; destructive action checks fail closed.
 */
class SystemPermissionRepository
{
    public const SYSTEM_KEYS = ['fleet', 'wells', 'valves'];

    public const DELETE_WELL_READING = 'wells.readings.delete';

    public const DELETE_DIESEL_INVENTORY = 'wells.diesel-inventories.delete';

    public const ACTION_KEYS = [
        self::DELETE_WELL_READING,
        self::DELETE_DIESEL_INVENTORY,
    ];

    public const PERMISSION_KEYS = [
        'fleet',
        'wells',
        'valves',
        self::DELETE_WELL_READING,
        self::DELETE_DIESEL_INVENTORY,
    ];

    public function __construct(private readonly OracleReadiness $readiness) {}

    /**
     * Check if a user has access to a specific system.
     *
     * @param int    $userId    — ID from mog_users.users_list
     * @param string $systemKey — 'fleet' | 'wells' | 'valves'
     */
    public function hasAccess(int $userId, string $systemKey): bool
    {
        $this->assertValidSystem($systemKey);

        if (! $this->readiness->available()) {
            Log::warning('SystemPermissionRepository: Oracle unavailable, granting access by default.', [
                'user_id' => $userId, 'system' => $systemKey,
            ]);
            return true;
        }

        try {
            return SystemPermission::query()
                ->where('user_id', $userId)
                ->where('system_key', $systemKey)
                ->exists();
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                Log::warning('SystemPermissionRepository: SYSTEM_PERMISSIONS table not found. Run the Oracle SQL script.', [
                    'user_id' => $userId, 'system' => $systemKey,
                ]);
                return true; // Fail open until table is created
            }
            throw $e;
        }
    }

    /**
     * Destructive action permissions fail closed when Oracle is unavailable.
     */
    public function hasActionPermission(int $userId, string $permissionKey): bool
    {
        $this->assertValidAction($permissionKey);

        if (! $this->readiness->available()) {
            return false;
        }

        try {
            return SystemPermission::query()
                ->where('user_id', $userId)
                ->where('system_key', $permissionKey)
                ->exists();
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Return all system keys accessible to a user.
     *
     * @return Collection<string>
     */
    public function accessibleSystems(int $userId): Collection
    {
        if (! $this->readiness->available()) {
            return collect(['fleet', 'wells', 'valves']);
        }

        try {
            return SystemPermission::query()
                ->where('user_id', $userId)
                ->whereIn('system_key', self::SYSTEM_KEYS)
                ->pluck('system_key');
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return collect(['fleet', 'wells', 'valves']); // Fail open
            }
            throw $e;
        }
    }

    /**
     * Grant a user access to a system.
     */
    public function grant(int $userId, string $systemKey, string $grantedBy): SystemPermission
    {
        $this->assertValidPermission($systemKey);

        return SystemPermission::query()->firstOrCreate(
            ['user_id' => $userId, 'system_key' => $systemKey],
            ['granted_by' => $grantedBy]
        );
    }

    /**
     * Revoke a user's access to a system.
     */
    public function revoke(int $userId, string $systemKey): void
    {
        $this->assertValidPermission($systemKey);

        $keys = $systemKey === 'wells'
            ? array_merge(['wells'], self::ACTION_KEYS)
            : [$systemKey];

        SystemPermission::query()
            ->where('user_id', $userId)
            ->whereIn('system_key', $keys)
            ->delete();
    }

    /**
     * Replace all system permissions for one user.
     *
     * @param array<int, string> $systemKeys
     */
    public function replaceForUser(int $userId, array $systemKeys, string $grantedBy): void
    {
        $systemKeys = array_values(array_unique($systemKeys));
        foreach ($systemKeys as $systemKey) {
            $this->assertValidPermission($systemKey);
        }

        if (array_intersect($systemKeys, self::ACTION_KEYS)
            && ! in_array('wells', $systemKeys, true)
        ) {
            throw new \InvalidArgumentException('Wells action permissions require wells access.');
        }

        DB::connection('oracle')->transaction(function () use ($userId, $systemKeys, $grantedBy): void {
            $deleteQuery = SystemPermission::query()->where('user_id', $userId);

            if ($systemKeys === []) {
                $deleteQuery->delete();
            } else {
                $deleteQuery
                    ->whereNotIn('system_key', $systemKeys)
                    ->delete();
            }

            foreach ($systemKeys as $systemKey) {
                $this->grant($userId, $systemKey, $grantedBy);
            }
        });
    }

    /**
     * All permissions ordered newest first (for admin management UI).
     */
    public function all(): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        try {
            return SystemPermission::query()
                ->orderByDesc('granted_at')
                ->get();
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return collect();
            }
            throw $e;
        }
    }

    /**
     * Permissions grouped by SSO user for the central portal UI.
     */
    public function groupedByUser(): Collection
    {
        $permissions = $this->all();
        $userIds = $permissions
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $users = $this->usersById($userIds);

        return $permissions
            ->groupBy(fn (SystemPermission $permission): int => (int) $permission->user_id)
            ->map(function (Collection $items, int $userId) use ($users): array {
                $user = $users->get($userId);

                return [
                    'user_id' => $userId,
                    'name' => $user?->name,
                    'email' => $user?->email,
                    'systems' => $items->pluck('system_key')->values()->all(),
                    'permissions' => $items->map(fn (SystemPermission $permission): array => [
                        'id' => $permission->getKey(),
                        'system_key' => $permission->system_key,
                        'granted_by' => $permission->granted_by,
                        'granted_at' => optional($permission->granted_at)->toISOString(),
                    ])->values()->all(),
                ];
            })
            ->sortBy('user_id')
            ->values();
    }

    /**
     * Search SSO users for the central permission UI.
     */
    public function searchUsers(string $query): Collection
    {
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return collect();
        }

        try {
            return MogSsoUser::query()
                ->where('is_active', 1)
                ->where(function ($builder) use ($query): void {
                    $builder
                        ->where('full_name', 'like', '%' . $query . '%')
                        ->when(is_numeric($query), function ($builder) use ($query): void {
                            $builder
                                ->orWhere('id', (int) $query)
                                ->orWhere('emp_id', (int) $query);
                        });
                })
                ->select('id', 'emp_id', 'full_name', 'user_mobile')
                ->limit(15)
                ->get()
                ->map(fn (MogSsoUser $user): array => [
                    'id' => (int) $user->getKey(),
                    'emp_id' => $user->emp_id,
                    'full_name' => $user->full_name,
                    'user_mobile' => $user->user_mobile,
                ]);
        } catch (QueryException) {
            return collect();
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isTableMissing(QueryException $e): bool
    {
        return (int) $e->getCode() === 942
            || str_contains($e->getMessage(), 'ORA-00942');
    }

    private function assertValidSystem(string $systemKey): void
    {
        if (! in_array($systemKey, self::SYSTEM_KEYS, true)) {
            throw new \InvalidArgumentException("Invalid system key [{$systemKey}].");
        }
    }

    private function assertValidAction(string $permissionKey): void
    {
        if (! in_array($permissionKey, self::ACTION_KEYS, true)) {
            throw new \InvalidArgumentException("Invalid action permission [{$permissionKey}].");
        }
    }

    private function assertValidPermission(string $permissionKey): void
    {
        if (! in_array($permissionKey, self::PERMISSION_KEYS, true)) {
            throw new \InvalidArgumentException("Invalid permission key [{$permissionKey}].");
        }
    }

    /**
     * @param Collection<int, int> $userIds
     */
    private function usersById(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        try {
            return MogSsoUser::query()
                ->whereIn('id', $userIds->all())
                ->get(['id', 'full_name as name', 'user_mobile as email'])
                ->keyBy(fn (MogSsoUser $user): int => (int) $user->getKey());
        } catch (QueryException) {
            return collect();
        }
    }
}
