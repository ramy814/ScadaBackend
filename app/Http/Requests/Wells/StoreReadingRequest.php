<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'WELL_OID' => ['required', 'integer', 'exists:wells,OID'],
            'INSPECTOR_OID' => ['required', 'integer', 'exists:inspectors,OID'],
            'FLOW_RATE' => ['nullable', 'numeric', 'min:0'],
            'GENERATOR_HOURS_READING' => ['required', 'numeric', 'min:0'],
            'PUMP_HOURS_READING' => ['required', 'numeric', 'min:0'],
            'CHLORINE' => ['required', 'numeric', 'min:0'],
            'SALINITY' => ['required', 'numeric', 'min:0'],
            'QUALITY' => ['required', 'string', 'in:excellent,good,acceptable,poor'],
            'READING_DATE' => ['required', 'date'],
            'LATITUDE' => ['nullable', 'numeric', 'between:-90,90'],
            'LONGITUDE' => ['nullable', 'numeric', 'between:-180,180'],
            'generator_logs' => ['nullable', 'array'],
            'generator_logs.*.GENERATOR_OID' => ['required', 'integer'],
            'generator_logs.*.CURRENT_READING' => ['required', 'numeric'],
            'generator_logs.*.GALLONS' => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'WELL_OID.required' => 'البئر مطلوب',
            'WELL_OID.exists' => 'البئر المحدد غير موجود',
            'GENERATOR_HOURS_READING.required' => 'قراءة عداد المولد مطلوبة',
            'PUMP_HOURS_READING.required' => 'قراءة عداد مضخة البئر مطلوبة',
            'CHLORINE.required' => 'نسبة الكلور مطلوبة',
            'SALINITY.required' => 'نسبة الملوحة مطلوبة',
            'QUALITY.required' => 'جودة المياه مطلوبة',
            'QUALITY.in' => 'جودة المياه يجب أن تكون ممتازة، جيدة، مقبولة، أو ضعيفة',
            'READING_DATE.required' => 'تاريخ القراءة مطلوب',
        ];
    }
}
