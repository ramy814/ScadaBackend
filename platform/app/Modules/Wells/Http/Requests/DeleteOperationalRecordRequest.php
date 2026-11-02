<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteOperationalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'سبب الحذف مطلوب للتدقيق.',
            'reason.min' => 'سبب الحذف يجب أن يكون واضحاً من 3 أحرف على الأقل.',
            'reason.max' => 'سبب الحذف يجب ألا يتجاوز 1000 حرف.',
        ];
    }
}
