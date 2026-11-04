<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonthlyDieselReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
            'tank_group' => ['nullable', Rule::in(['internal', 'external'])],
            'well_oid' => ['nullable', 'integer'],
            'generator_oid' => ['nullable', 'integer'],
            'columns' => ['nullable', 'array', 'max:20'],
            'columns.*' => ['string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.date_format' => 'صيغة الشهر المحدد غير صحيحة',
            'tank_group.in' => 'نوع الخزان المحدد غير صالح',
            'columns.array' => 'قائمة أعمدة التقرير غير صالحة',
            'columns.max' => 'عدد أعمدة التقرير أكبر من الحد المسموح',
        ];
    }
}
