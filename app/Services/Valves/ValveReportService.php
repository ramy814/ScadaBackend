<?php

declare(strict_types=1);

namespace App\Services\Valves;

use App\Models\Valves\ValveLog;
use Illuminate\Pagination\LengthAwarePaginator;

class ValveReportService
{
    /**
     * Get paginated logs with filters.
     *
     * @param array $filters Filters: inspector_oid, team_oid, action, valve_code, date_from, date_to
     */
    public function getLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ValveLog::with(['inspector', 'valve']);

        // Filter by specific inspector
        if (!empty($filters['inspector_oid'])) {
            $query->where('USER_OID', $filters['inspector_oid']);
        }

        // Filter by team (find inspectors in this team)
        if (!empty($filters['team_oid'])) {
            $teamOid = $filters['team_oid'];
            $query->whereHas('inspector.teams', function ($q) use ($teamOid) {
                $q->where('valve_teams.oid', $teamOid);
            });
        }

        // Filter by specific action type (e.g., open, close, maintenance, replacement)
        if (!empty($filters['action'])) {
            $query->where('ACTION', $filters['action']);
        }

        // Filter by Valve Code
        if (!empty($filters['valve_code'])) {
            $valveCode = strtoupper($filters['valve_code']);
            if (str_starts_with($valveCode, 'WV-')) {
                $valveOid = (int) str_replace('WV-', '', $valveCode);
                $query->where('VALVE_OID', $valveOid);
            } else {
                // If they enter just the number or part of it, try casting to int
                $valveOid = (int) $valveCode;
                if ($valveOid > 0) {
                    $query->where('VALVE_OID', $valveOid);
                }
            }
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('ACTION_AT', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('ACTION_AT', '<=', $filters['date_to']);
        }

        return $query->orderBy('ACTION_AT', 'desc')->paginate($perPage)->withQueryString();
    }
}
