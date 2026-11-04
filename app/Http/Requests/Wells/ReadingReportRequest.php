<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class ReadingReportRequest extends FormRequest
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
            'quality' => ['nullable', 'string', 'in:excellent,good,acceptable,poor'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'columns' => ['nullable', 'array', 'max:20'],
            'columns.*' => ['string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساويًا له',
            'columns.array' => 'قائمة أعمدة التقرير غير صالحة',
            'columns.max' => 'عدد أعمدة التقرير أكبر من الحد المسموح',
        ];
    }
}
