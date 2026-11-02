<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInspectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $inspectorId = $this->route('inspector');

        return [
            'NAME' => ['sometimes', 'required', 'string', 'max:255'],
            'PHONE' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('inspectors', 'PHONE')->ignore($inspectorId, 'OID'),
            ],
            'PASSWORD' => ['nullable', 'string', 'min:6'],
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
            'PASSWORD.min' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل',
        ];
    }
}
