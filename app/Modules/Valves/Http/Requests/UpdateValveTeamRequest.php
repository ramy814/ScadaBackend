<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateValveTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'NAME' => ['required', 'string', 'max:255'],
            'IS_ACTIVE' => ['boolean'],
            'ASSIGN_ALL_VALVES' => ['boolean'],
            'valve_ids' => ['nullable', 'array'],
            'valve_ids.*' => ['integer', Rule::exists('oracle.EME.WATER_VALVES', 'W4_GENERAL_ID')],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم الفريق مطلوب',
            'valve_ids.*.exists' => 'أحد المحابس المحددة غير موجود',
        ];
    }
}
