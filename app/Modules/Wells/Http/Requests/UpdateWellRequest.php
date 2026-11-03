<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $generators = $this->input('generators');

        if (! is_array($generators)) {
            return;
        }

        $this->merge([
            'generators' => array_map(static function (mixed $generator): mixed {
                if (is_numeric($generator)) {
                    return [
                        'GENERATOR_OID' => $generator,
                        'FUEL_CONSUMPTION_LPH' => null,
                        'CONSUMPTION_TOLERANCE_LPH' => null,
                    ];
                }

                if (is_object($generator)) {
                    $generator = (array) $generator;
                }

                if (! is_array($generator)) {
                    return $generator;
                }

                return [
                    'GENERATOR_OID' => $generator['GENERATOR_OID']
                        ?? $generator['generator_oid']
                        ?? $generator['OID']
                        ?? $generator['oid']
                        ?? null,
                    'FUEL_CONSUMPTION_LPH' => $generator['FUEL_CONSUMPTION_LPH']
                        ?? $generator['fuel_consumption_lph']
                        ?? null,
                    'CONSUMPTION_TOLERANCE_LPH' => $generator['CONSUMPTION_TOLERANCE_LPH']
                        ?? $generator['consumption_tolerance_lph']
                        ?? null,
                ];
            }, $generators),
        ]);
    }

    public function rules(): array
    {
        return [
            // البيانات العامة
            'WELL_NUM' => ['sometimes', 'nullable', 'string', 'max:100'],
            'NAME' => ['sometimes', 'required', 'string', 'max:255'],
            'QUARTER_OID' => ['nullable', 'integer'],
            'DAMAGE_STATUS' => ['nullable', 'string', 'in:سليمة,متضررة,أعيد تأهيلها,مدمرة'],
            'OPERATIONAL_STATUS' => ['nullable', 'string', 'in:يعمل,متوقف,تحت الصيانة'],
            // بيانات البئر والمضخة
            'WELL_DEPTH' => ['nullable', 'numeric', 'min:0'],
            'PUMP_TYPE' => ['sometimes', 'required', 'string', 'in:submersible,vertical'],
            'PUMP_POWER_HP' => ['nullable', 'numeric', 'min:0'],
            'CAPACITY' => ['sometimes', 'required', 'numeric', 'min:0'],
            'SALINITY' => ['nullable', 'numeric', 'min:0'],
            // الطاقة والموقع
            'POWER_SOURCE' => ['sometimes', 'required', 'string', 'in:electricity,solar,generator'],
            'LATITUDE' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'LONGITUDE' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'IS_ACTIVE' => ['boolean'],
            // بيانات خزان الوقود
            'TANK_SHAPE' => ['nullable', 'string', 'in:اسطواني عمودي,اسطواني أفقي,مكعب,Daily Tank'],
            'TANK_MATERIAL' => ['nullable', 'string', 'in:HDPE,معدن,فيبرجلاس'],
            'TANK_DIAMETER' => ['nullable', 'numeric', 'min:0'],
            'TANK_HEIGHT' => ['nullable', 'numeric', 'min:0'],
            // نظام الكلورة
            'CHLORINATION_SYSTEM' => ['nullable', 'string', 'in:يوجد,لا يوجد'],
            'CHLORINATION_STATUS' => ['nullable', 'string', 'in:يعمل,لا يعمل'],
            'CHLORINE_TANK_CAPACITY' => ['nullable', 'numeric', 'min:0'],
            'CHLORINE_TANK_TYPE' => ['nullable', 'string', 'in:اسطواني عمودي,مكعب'],
            'CHLORINE_TANK_MATERIAL' => ['nullable', 'string', 'in:HDPE أزرق,بلاستيك شفاف'],
            'CHLORINE_PUMP_TYPE' => ['nullable', 'string', 'max:100'],
            // خزان التبريد
            'COOLING_TANK_CAPACITY' => ['nullable', 'numeric', 'in:220,500'],
            'COOLING_TANK_TYPE' => ['nullable', 'string', 'in:HDPE أزرق,HDPE أسود'],
            'COOLING_SYSTEM' => ['nullable', 'string', 'in:اوتوماتيك,يدوي,غير متوفر,غاطس,اوتوماتيك يحتاج صيانة'],
            'COOLING_LINE_TYPE' => ['nullable', 'string', 'in:حديد سكب,HDPE'],
            'generators' => ['nullable', 'array'],
            'generators.*' => ['required', 'array'],
            'generators.*.GENERATOR_OID' => ['required', 'integer'],
            'generators.*.FUEL_CONSUMPTION_LPH' => ['nullable', 'numeric', 'min:0'],
            'generators.*.CONSUMPTION_TOLERANCE_LPH' => ['nullable', 'numeric', 'min:0'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*.category' => ['required', 'string', 'in:pump,fuel_tank,cooling,valves_parts,well_room'],
            'media.*.label' => ['nullable', 'string', 'max:255'],
            'media.*.file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required' => 'اسم البئر مطلوب',
            'PUMP_TYPE.in' => 'نوع المضخة يجب أن يكون غاطس أو رأسي',
            'POWER_SOURCE.in' => 'مصدر الطاقة يجب أن يكون كهرباء أو طاقة شمسية أو مولد',
            'generators.*.GENERATOR_OID.required' => 'معرّف المولد مطلوب',
            'generators.*.FUEL_CONSUMPTION_LPH.min' => 'معدل استهلاك المولد يجب ألا يقل عن صفر',
            'generators.*.CONSUMPTION_TOLERANCE_LPH.min' => 'فرق استهلاك المولد يجب ألا يقل عن صفر',
            'media.*.file.image' => 'يجب أن يكون الملف المرفوع صورة',
            'media.*.file.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت',
        ];
    }
}
