<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $icons = [
            'danger',
            'warning',
            'success',
            'info',
            'bell',
            'maint',
            'fuel',
            'water',
            'power',
            'repair',
        ];

        return [
            'NAME' => ['required', 'string', 'max:255'],
            'ICON' => ['required', 'string', Rule::in($icons)],
            'EMOJI' => ['nullable', 'string', Rule::in($icons)],
            'COLOR' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'PRIORITY' => ['required', 'integer', 'min:1', 'max:100'],
            'IS_ACTIVE' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم نوع الإشعار مطلوب',
            'ICON.required' => 'أيقونة نوع الإشعار مطلوبة',
            'ICON.in' => 'أيقونة نوع الإشعار غير معتمدة',
            'EMOJI.in' => 'أيقونة نوع الإشعار غير معتمدة',
            'COLOR.required' => 'لون نوع الإشعار مطلوب',
            'COLOR.regex' => 'لون نوع الإشعار غير صالح',
            'PRIORITY.required' => 'أولوية نوع الإشعار مطلوبة',
            'PRIORITY.min' => 'الأولوية يجب ألا تقل عن 1',
            'PRIORITY.max' => 'الأولوية يجب ألا تزيد عن 100',
        ];
    }
}
