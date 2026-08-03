<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class ReadingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'well_oid' => ['nullable', 'integer'],
            'inspector_oid' => ['nullable', 'integer'],
            'quality' => ['nullable', 'string', 'in:excellent,good,acceptable,poor'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ];
    }
}
