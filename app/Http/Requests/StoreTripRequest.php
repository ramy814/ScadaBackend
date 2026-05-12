<?php

namespace App\Http\Requests;

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
            'points_expected' => ['nullable', 'integer', 'min:0'],
            'passengers' => ['array'],
            'passengers.*.employee_id' => ['nullable', 'string', 'max:120'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.source' => ['required', 'in:employee,free_text'],
            'stops' => ['array'],
            'stops.*.started_at' => ['required', 'date'],
            'stops.*.ended_at' => ['nullable', 'date'],
            'stops.*.reason' => ['nullable', 'string', 'max:255'],
            'stops.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

