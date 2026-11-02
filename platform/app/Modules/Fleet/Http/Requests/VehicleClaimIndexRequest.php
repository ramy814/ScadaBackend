<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VehicleClaimIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'vehicle_search' => ['nullable', 'string', 'max:120'],
            'include_unpriced' => ['nullable', 'boolean'],
        ];
    }
}
