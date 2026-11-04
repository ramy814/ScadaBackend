<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'custom'])],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'required_if:period,custom',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
            'response_status' => ['nullable', Rule::in(['all', 'pending', 'responded'])],
            'trip_source' => ['nullable', Rule::in(['mobile', 'manual'])],
            'search' => ['nullable', 'string', 'max:120'],
            'show_experimental' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
