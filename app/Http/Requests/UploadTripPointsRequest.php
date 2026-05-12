<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadTripPointsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'points' => ['required', 'array', 'min:1', 'max:500'],
            'points.*.sequence_number' => ['required', 'integer', 'min:1'],
            'points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'points.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'points.*.speed' => ['nullable', 'numeric', 'min:0'],
            'points.*.heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'points.*.battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'points.*.provider' => ['nullable', 'string', 'max:50'],
            'points.*.captured_at' => ['required', 'date'],
        ];
    }
}

