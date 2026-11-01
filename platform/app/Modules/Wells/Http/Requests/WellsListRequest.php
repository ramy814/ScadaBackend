<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WellsListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'damage_status' => ['nullable', 'in:سليمة,متضررة,أعيد تأهيلها,مدمرة'],
            'is_active' => ['nullable', 'in:0,1'],
            'state' => ['nullable', 'in:all,unread'],
        ];
    }
}
