<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveManualTripRequest extends FormRequest
{
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:1000']];
    }
}
