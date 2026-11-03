<?php

declare(strict_types=1);

namespace App\Platform\Audit;

use Illuminate\Foundation\Http\FormRequest;

class AuditFilterRequest extends FormRequest
{
    public function rules(): array
    {
        return ['event' => ['nullable', 'string', 'max:64'], 'entity' => ['nullable', 'string', 'max:20']];
    }
}
