<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemOperationalStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shutdown_enabled' => ['required', 'boolean'],
            'reason' => ['nullable', 'required_if:shutdown_enabled,true', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shutdown_enabled.required' => 'حالة تشغيل الأنظمة مطلوبة.',
            'shutdown_enabled.boolean' => 'حالة تشغيل الأنظمة غير صحيحة.',
            'reason.required_if' => 'سبب الإيقاف الشامل مطلوب.',
            'reason.min' => 'سبب الإيقاف يجب ألا يقل عن 3 أحرف.',
            'reason.max' => 'سبب الإيقاف يجب ألا يزيد عن 1000 حرف.',
        ];
    }
}
