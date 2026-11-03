<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Rules the legacy Api\Wells\AuthController::login() validated inline (framework messages). */
class WellsLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
