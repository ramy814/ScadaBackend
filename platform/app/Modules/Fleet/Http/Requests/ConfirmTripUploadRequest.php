<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTripUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'points_expected' => ['required', 'integer', 'min:0'],
            'checksum' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'checksum_version' => ['nullable', 'integer', 'in:1,2'],
        ];
    }
}
