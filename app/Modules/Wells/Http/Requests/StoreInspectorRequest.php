<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInspectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255'],
            'PHONE' => ['required', 'string', 'max:20', 'unique:inspectors,PHONE'],
            'PASSWORD' => ['required', 'string', 'min:6'],
            'IS_ACTIVE' => ['boolean'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,OID'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم المفتش مطلوب',
            'PHONE.required' => 'رقم الهاتف مطلوب',
            'PHONE.unique' => 'رقم الهاتف مسجل مسبقاً',
            'PASSWORD.required' => 'كلمة المرور مطلوبة',
            'PASSWORD.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
        ];
    }
}
