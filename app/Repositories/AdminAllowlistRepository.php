<?php

namespace App\Repositories;

use App\Models\AdminAllowlist;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Manages the FLEET_ADMIN_ALLOWLIST table in Oracle.
 * All dashboard access-control queries go through here.
 */
class AdminAllowlistRepository
{
    public function __construct(private readonly OracleReadiness $readiness) {}

    /**
     * Check whether the given employee number is allowed to access the dashboard.
     * Returns true (allow all) when Oracle is unavailable or table doesn't exist yet,
     * so a missing table / connection issue never hard-locks everyone out.
     */
    public function isAllowed(string $empNo): bool
    {
        if (! $this->readiness->available()) {
            Log::warning('AdminAllowlistRepository: Oracle unavailable, skipping allowlist check.');
            return true;
        }

        try {
            return AdminAllowlist::query()
                ->where('emp_no', $empNo)
                ->exists();
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                Log::warning('AdminAllowlistRepository: FLEET_ADMIN_ALLOWLIST table does not exist yet. Run 002_create_admin_allowlist.sql on Oracle.');
                return true; // Fail open until table is created
            }
            throw $e;
        }
    }

    /**
     * Return all allowlisted entries ordered by creation date (newest first).
     */
    public function all(): Collection
    {
        if (! $this->readiness->available()) {
            return collect();
        }

        try {
            return AdminAllowlist::query()
                ->orderByDesc('created_at')
                ->get();
        } catch (QueryException $e) {
            if ($this->isTableMissing($e)) {
                return collect(); // Table not created yet — return empty list
            }
            throw $e;
        }
    }

    /**
     * Add an employee number to the allowlist.
     */
    public function add(string $empNo, ?string $label, string $addedBy): AdminAllowlist
    {
        return AdminAllowlist::query()->create([
            'emp_no'   => $empNo,
            'label'    => $label,
            'added_by' => $addedBy,
        ]);
    }

    /**
     * Remove an entry from the allowlist by its primary key.
     */
    public function remove(int $id): void
    {
        AdminAllowlist::query()->findOrFail($id)->delete();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function isTableMissing(QueryException $e): bool
    {
        return (int) $e->getCode() === 942
            || str_contains($e->getMessage(), 'ORA-00942');
    }
}

