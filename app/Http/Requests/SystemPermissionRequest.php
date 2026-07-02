<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\SystemPermissionRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemPermissionRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'min:1'],
            'systems' => ['array'],
            'systems.*' => ['string', Rule::in(SystemPermissionRepository::SYSTEM_KEYS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'رقم المستخدم مطلوب.',
            'user_id.integer' => 'رقم المستخدم يجب أن يكون رقماً صحيحاً.',
            'systems.array' => 'الصلاحيات يجب أن ترسل كقائمة أنظمة.',
            'systems.*.in' => 'النظام المحدد غير معروف.',
        ];
    }
}
