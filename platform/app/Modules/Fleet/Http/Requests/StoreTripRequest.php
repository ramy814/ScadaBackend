<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'local_id' => ['required', 'uuid'],
            'vehicle_plate' => ['required', 'string', 'max:30'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'origin.type' => ['required', 'in:known,free_text'],
            'origin.id' => ['nullable', 'string', 'max:120'],
            'origin.text' => ['required', 'string', 'max:255'],
            'destination.type' => ['required', 'in:known,free_text'],
            'destination.id' => ['nullable', 'string', 'max:120'],
            'destination.text' => ['required', 'string', 'max:255'],
            'mission_type' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'start_odometer' => ['nullable', 'integer', 'min:0'],
            'end_odometer' => ['nullable', 'integer', 'min:0'],
            'wait_minutes' => ['nullable', 'integer', 'min:0'],
            'manual_wait_minutes' => ['nullable', 'integer', 'min:0'],
            'points_expected' => ['nullable', 'integer', 'min:0'],
            'quality_version' => ['nullable', 'integer', 'in:2'],
            'passengers' => ['array', 'max:50'],
            'passengers.*.employee_id' => ['nullable', 'string', 'max:120'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.source' => ['required', 'in:employee,free_text'],
            'stops' => ['array', 'max:500'],
            'stops.*.local_id' => ['nullable', 'uuid', 'distinct'],
            'stops.*.started_at' => ['required', 'date'],
            'stops.*.ended_at' => ['nullable', 'date'],
            'stops.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'stops.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'stops.*.source' => ['nullable', 'in:automatic,manual'],
            'stops.*.reason' => ['nullable', 'string', 'max:255'],
            'stops.*.notes' => ['nullable', 'string', 'max:1000'],
            'tracking_events' => ['array', 'max:500'],
            'tracking_events.*.local_id' => ['required', 'uuid', 'distinct'],
            'tracking_events.*.event_type' => [
                'required',
                'in:gps_disabled,gps_enabled,airplane_mode_on,airplane_mode_off,device_shutdown,device_boot,battery_low,location_permission_missing,location_permission_restored,tracking_error,tracking_gap,clock_anomaly',
            ],
            'tracking_events.*.detected_at' => ['required', 'date'],
            'tracking_events.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'tracking_events.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tracking_events.*.details_json' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
