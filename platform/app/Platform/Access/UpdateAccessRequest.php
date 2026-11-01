<?php

declare(strict_types=1);

namespace App\Platform\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'keys' => ['present', 'array'],
            'keys.*' => ['string', Rule::in(SystemPermissionRepository::PERMISSION_KEYS)],
        ];
    }

    /** Sensitive Wells actions require base Wells access (legacy ACTION_REQUIREMENTS). */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = (array) $this->input('keys', []);
            foreach (SystemPermissionRepository::ACTION_REQUIREMENTS as $action => $system) {
                if (in_array($action, $keys, true) && ! in_array($system, $keys, true)) {
                    $validator->errors()->add('keys', __('access.requires_system', ['system' => $system]));
                }
            }
        }];
    }
}
