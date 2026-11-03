<?php

declare(strict_types=1);

namespace App\Platform\Operations;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperationalStateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'shutdown_enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'required_if:shutdown_enabled,true,1', 'string', 'min:3', 'max:1000'],
            'confirmation' => ['required_if:shutdown_enabled,true,1', 'accepted_if:shutdown_enabled,true,1'],
        ];
    }
}
