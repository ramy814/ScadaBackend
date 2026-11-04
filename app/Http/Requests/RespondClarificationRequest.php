<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RespondClarificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'max:2000'],
        ];
    }
}

