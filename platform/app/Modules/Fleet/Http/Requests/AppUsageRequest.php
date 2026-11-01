<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppUsageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'version_status' => ['nullable', 'in:current,outdated,not_reported'],
            'install_source' => ['nullable', 'in:store,external,unknown'],
            'activity' => ['nullable', 'in:recent,inactive'],
        ];
    }
}
