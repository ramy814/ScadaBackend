<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DecisionRequest extends FormRequest
{
    public function rules(): array
    {
        // A clarification is a question to the driver, so its text is mandatory (legacy rule).
        return [
            'note' => [$this->route('decision') === 'clarify' ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => __('fleet.errors.question_required')];
    }
}
