<?php

declare(strict_types=1);

namespace App\Modules\Valves\Http\Requests;

use App\Modules\Valves\Services\ValvesService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValvesListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in([...ValvesService::MAINTENANCE_STATUSES, 'all'])],
            'action' => ['nullable', Rule::in(ValvesService::ACTIONS)],
            'valve' => ['nullable', 'string', 'max:20'],
            'team' => ['nullable', 'integer'],
            'inspector' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
