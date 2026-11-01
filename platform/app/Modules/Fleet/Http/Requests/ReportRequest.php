<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'custom'])],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'driver' => ['nullable', 'string', 'max:100'],
            'vehicle' => ['nullable', 'string', 'max:100'],
        ];
    }
}
