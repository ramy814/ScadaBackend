<?php

declare(strict_types=1);

namespace App\Services\Valves;

use App\Models\Valves\Valve;
use App\Models\Valves\ValveLog;
use App\Models\Valves\ValveMaintenanceReport;

class ValvesDashboardService
{
    public function getStats(): array
    {
        $totalValves = Valve::count();
        $latestActions = ValveLog::orderBy('ACTION_AT', 'desc')
            ->get(['VALVE_OID', 'ACTION'])
            ->unique('valve_oid');

        $openValves = $latestActions->where('action', 'open')->count();
        $closedValves = $latestActions->where('action', 'close')->count();
        $maintenanceValves = $latestActions->where('action', 'maintenance')->count();
        $replacementValves = $latestActions->where('action', 'replacement')->count();

        $totalLogs = ValveLog::count();
        $todayLogs = ValveLog::whereDate('ACTION_AT', today())->count();

        $pendingReports = ValveMaintenanceReport::where('STATUS', 'pending')->count();
        $inProgressReports = ValveMaintenanceReport::where('STATUS', 'in_progress')->count();
        $resolvedReports = ValveMaintenanceReport::where('STATUS', 'resolved')->count();

        // Recent maintenance reports with valve info
        $recentReports = ValveMaintenanceReport::with('valve')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($report) => [
                'oid'           => $report->oid,
                'valve_code'    => $report->valve?->code ?? '-',
                'valve_name'    => $report->valve?->name ?? '-',
                'valve_area'    => $report->valve?->area ?? '-',
                'description'   => $report->description,
                'priority'      => $report->priority,
                'status'        => $report->status,
                'created_at'    => $report->created_at,
            ])
            ->toArray();

        // Recent valve actions (logs)
        $recentActions = ValveLog::with('valve')
            ->orderBy('ACTION_AT', 'desc')
            ->take(10)
            ->get()
            ->map(fn($log) => [
                'oid'        => $log->oid,
                'valve_code' => $log->valve?->code ?? '-',
                'valve_name' => $log->valve?->name ?? '-',
                'action'     => $log->action,
                'note'       => $log->note,
                'action_at'  => $log->action_at,
            ])
            ->toArray();

        $mapValves = [];

        return [
            'totalValves'       => $totalValves,
            'openValves'        => $openValves,
            'closedValves'      => $closedValves,
            'maintenanceValves' => $maintenanceValves,
            'replacementValves' => $replacementValves,
            'totalLogs'         => $totalLogs,
            'todayLogs'         => $todayLogs,
            'pendingReports'    => $pendingReports,
            'inProgressReports' => $inProgressReports,
            'resolvedReports'   => $resolvedReports,
            'recentReports'     => $recentReports,
            'recentActions'     => $recentActions,
            'mapValves'         => $mapValves,
        ];
    }
}
