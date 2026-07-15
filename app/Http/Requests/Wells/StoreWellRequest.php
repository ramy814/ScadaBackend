<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class StoreWellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // البيانات العامة
            'WELL_NUM' => ['nullable', 'string', 'max:100'],
            'NAME' => ['required', 'string', 'max:255'],
            'QUARTER_OID' => ['nullable', 'integer'],
            'DAMAGE_STATUS' => ['nullable', 'string', 'in:سليمة,متضررة,أعيد تأهيلها,مدمرة'],
            'OPERATIONAL_STATUS' => ['nullable', 'string', 'in:يعمل,متوقف,تحت الصيانة'],
            'INSTALLATION_DATE' => ['nullable', 'date'],
            'LAST_MAINTENANCE_DATE' => ['nullable', 'date'],
            // بيانات البئر والمضخة
            'WELL_DEPTH' => ['nullable', 'numeric', 'min:0'],
            'PUMP_TYPE' => ['required', 'string', 'in:submersible,vertical'],
            'PUMP_POWER_HP' => ['nullable', 'numeric', 'min:0'],
            'CAPACITY' => ['required', 'numeric', 'min:0'],
            'SALINITY' => ['nullable', 'numeric', 'min:0'],
            // الطاقة والموقع
            'POWER_SOURCE' => ['required', 'string', 'in:electricity,solar,generator'],
            'LATITUDE' => ['required', 'numeric', 'between:-90,90'],
            'LONGITUDE' => ['required', 'numeric', 'between:-180,180'],
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
            'generators.*' => ['integer'],
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
            'PUMP_TYPE.required' => 'نوع المضخة مطلوب',
            'PUMP_TYPE.in' => 'نوع المضخة يجب أن يكون غاطس أو Vertical',
            'CAPACITY.required' => 'الإنتاجية مطلوبة',
            'POWER_SOURCE.required' => 'مصدر الطاقة مطلوب',
            'POWER_SOURCE.in' => 'مصدر الطاقة يجب أن يكون كهرباء أو طاقة شمسية أو مولد',
            'LATITUDE.required' => 'خط العرض مطلوب',
            'LONGITUDE.required' => 'خط الطول مطلوب',
            'media.*.file.image' => 'يجب أن يكون الملف المرفوع صورة',
            'media.*.file.max' => 'حجم الصورة يجب ألا يتجاوز 2 ميجابايت',
        ];
    }
}
