<?php

declare(strict_types=1);

namespace App\Http\Requests\Valves;

use Illuminate\Foundation\Http\FormRequest;

class StoreValveInspectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255'],
            'PHONE' => ['required', 'string', 'max:20', 'unique:valve_inspectors,PHONE'],
            'PASSWORD' => ['required', 'string', 'min:6'],
            'IS_ACTIVE' => ['boolean'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:valve_teams,OID'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم المفتش مطلوب',
            'PHONE.required' => 'رقم الجوال مطلوب',
            'PHONE.unique' => 'رقم الجوال مسجل مسبقاً في نظام المحابس',
            'PASSWORD.required' => 'كلمة المرور مطلوبة',
            'PASSWORD.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
            'team_ids.*.exists' => 'أحد الفرق المحددة غير موجود',
        ];
    }
}
