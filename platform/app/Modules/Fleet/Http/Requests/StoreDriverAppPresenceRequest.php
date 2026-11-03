<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverAppPresenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'uuid'],
            'app_version' => ['required', 'string', 'max:32'],
            'build_number' => ['required', 'integer', 'min:1'],
            'package_name' => ['required', 'string', 'max:191'],
            'installer_store' => ['nullable', 'string', 'max:191'],
            'installed_at' => ['nullable', 'date'],
            'app_updated_at' => ['nullable', 'date'],
        ];
    }
}
