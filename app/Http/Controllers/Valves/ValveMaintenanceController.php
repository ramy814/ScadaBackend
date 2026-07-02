<?php

namespace App\Http\Controllers\Valves;

use App\Http\Controllers\Controller;
use App\Models\Valves\ValveMaintenanceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValveMaintenanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ValveMaintenanceReport::with(['reporter:id,name,phone']);

        if ($request->has('status')) {
            $query->where('STATUS', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('PRIORITY', $request->priority);
        }

        $reports = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($reports);
    }

    public function update(Request $request, ValveMaintenanceReport $maintenanceReport): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,in_progress,resolved',
            'priority' => 'sometimes|in:low,medium,high,critical',
        ]);

        $dataToUpdate = [];
        if (isset($validated['status'])) {
            $dataToUpdate['STATUS'] = $validated['status'];
            if ($validated['status'] === 'resolved') {
                $dataToUpdate['RESOLVED_BY'] = $request->user()->id;
                $dataToUpdate['RESOLVED_AT'] = now();
            }
        }
        
        if (isset($validated['priority'])) {
            $dataToUpdate['PRIORITY'] = $validated['priority'];
        }

        if (!empty($dataToUpdate)) {
            $maintenanceReport->update($dataToUpdate);
        }

        return response()->json($maintenanceReport->load('reporter:id,name'));
    }
}
