<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DieselInventoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'well_oid' => ['nullable', 'integer'],
            'inspector_oid' => ['nullable', 'integer'],
            'generator_oid' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tank_group' => ['nullable', Rule::in(['internal', 'external'])],
            'external_view' => ['nullable', Rule::in(['inventory', 'results'])],
            'report_type' => [
                'nullable',
                Rule::in(['internal', 'external_inventory', 'external_results']),
            ],
            'columns' => ['nullable', 'array', 'max:30'],
            'columns.*' => ['string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساوياً له',
            'tank_group.in' => 'تصنيف الخزان المحدد غير صالح',
            'external_view.in' => 'تبويب الخزان الخارجي المحدد غير صالح',
            'report_type.in' => 'نوع تقرير جرد السولار غير صالح',
            'columns.array' => 'قائمة أعمدة التقرير غير صالحة',
            'columns.max' => 'عدد أعمدة التقرير أكبر من الحد المسموح',
        ];
    }
}
