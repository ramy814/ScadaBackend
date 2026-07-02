<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Valves\Valve;
use App\Models\Valves\ValveLog;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $latestActions = ValveLog::orderBy('ACTION_AT', 'desc')
            ->get(['VALVE_OID', 'ACTION'])
            ->unique('valve_oid');

        return response()->json([
            'valves' => [
                'total' => Valve::count(),
                'open' => $latestActions->where('action', 'open')->count(),
                'closed' => $latestActions->where('action', 'close')->count(),
                'maintenance' => $latestActions->where('action', 'maintenance')->count(),
                'replacement' => $latestActions->where('action', 'replacement')->count(),
            ],
            'wells' => [
                'total' => 0,
                'on' => 0,
                'off' => 0,
                'maintenance' => 0,
            ],
        ]);
    }

    public function mapData(): JsonResponse
    {
        $valves = Valve::with('latestLog')->get();

        return response()->json([
            'valves' => $valves,
            'wells' => [],
        ]);
    }

    public function recentActivity(): JsonResponse
    {
        $recentActions = ValveLog::with('valve')
            ->orderBy('ACTION_AT', 'desc')
            ->take(15)
            ->get()
            ->map(fn($log) => [
                'type' => 'valve',
                'oid' => $log->oid,
                'asset_code' => $log->valve?->code ?? '-',
                'valve_name' => $log->valve?->name ?? '-',
                'action' => $log->action,
                'note' => $log->note,
                'action_at' => $log->action_at,
            ]);

        return response()->json($recentActions);
    }
}
