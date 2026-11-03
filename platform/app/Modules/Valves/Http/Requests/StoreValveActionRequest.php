<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreValveActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'local_id' => ['nullable', 'string', 'max:64'],
            'action' => ['required', 'in:open,close,maintenance,replacement'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
