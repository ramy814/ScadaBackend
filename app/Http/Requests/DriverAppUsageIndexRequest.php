<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverAppUsageIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'version_status' => [
                'nullable',
                Rule::in(['current', 'outdated', 'not_reported']),
            ],
            'install_source' => [
                'nullable',
                Rule::in(['store', 'external', 'unknown']),
            ],
            'activity' => [
                'nullable',
                Rule::in(['recent', 'inactive']),
            ],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
