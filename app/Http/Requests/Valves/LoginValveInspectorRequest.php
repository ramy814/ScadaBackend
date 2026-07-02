<?php

declare(strict_types=1);

namespace App\Http\Requests\Valves;

use Illuminate\Foundation\Http\FormRequest;

class LoginValveInspectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الجوال مطلوب',
            'password.required' => 'كلمة المرور مطلوبة',
        ];
    }
}
