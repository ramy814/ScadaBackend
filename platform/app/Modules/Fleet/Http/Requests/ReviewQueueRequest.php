<?php

declare(strict_types=1);

namespace App\Modules\Fleet\Http\Requests;

use App\Modules\Fleet\Repositories\TripRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewQueueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'queue' => ['nullable', Rule::in(TripRepository::QUEUES)],
            'source' => ['nullable', Rule::in(['mobile', 'manual'])],
            'search' => ['nullable', 'string', 'max:100'],
            'experimental' => ['nullable', 'boolean'],
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'custom'])],
            'date_from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
