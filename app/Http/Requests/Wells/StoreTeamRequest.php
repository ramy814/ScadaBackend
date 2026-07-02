<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
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
            'well_ids' => ['nullable', 'array'],
            'well_ids.*' => ['integer', 'exists:wells,OID'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم الفريق مطلوب',
        ];
    }
}
