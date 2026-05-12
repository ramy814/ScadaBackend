<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTripUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'points_expected' => ['required', 'integer', 'min:0'],
            'checksum' => ['required', 'string', 'max:128'],
        ];
    }
}

