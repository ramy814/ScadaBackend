<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Same rules the legacy ValveMaintenanceController::update() validated inline. */
class UpdateMaintenanceReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:pending,in_progress,resolved',
            'priority' => 'sometimes|in:low,medium,high,critical',
        ];
    }
}
