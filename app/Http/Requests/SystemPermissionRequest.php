<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\SystemPermissionRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'systems.*' => ['string', Rule::in(SystemPermissionRepository::PERMISSION_KEYS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $permissions = $this->input('systems', []);

            if (! is_array($permissions)) {
                return;
            }

            foreach (SystemPermissionRepository::ACTION_REQUIREMENTS as $actionKey => $requiredSystem) {
                if (in_array($actionKey, $permissions, true)
                    && ! in_array($requiredSystem, $permissions, true)
                ) {
                    $validator->errors()->add(
                        'systems',
                        'الصلاحية الفرعية المحددة تتطلب منح صلاحية النظام التابعة له.'
                    );
                }
            }
        });
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
            'systems.*.in' => 'الصلاحية المحددة غير معروفة.',
        ];
    }
}
