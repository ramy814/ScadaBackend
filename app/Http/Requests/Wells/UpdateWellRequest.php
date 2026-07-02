<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // البيانات العامة
            'WELL_NUM'             => ['sometimes', 'nullable', 'string', 'max:100'],
            'NAME'                 => ['sometimes', 'required', 'string', 'max:255'],
            'QUARTER_OID'          => ['nullable', 'integer'],
            'DAMAGE_STATUS'        => ['nullable', 'string', 'in:سليمة,متضررة,أعيد تأهيلها,مدمرة'],
            'OPERATIONAL_STATUS'   => ['nullable', 'string', 'in:يعمل,متوقف,تحت الصيانة'],
            'INSTALLATION_DATE'    => ['nullable', 'date'],
            'LAST_MAINTENANCE_DATE'=> ['nullable', 'date'],
            // بيانات البئر والمضخة
            'WELL_DEPTH'           => ['nullable', 'numeric', 'min:0'],
            'PUMP_TYPE'            => ['sometimes', 'required', 'string', 'in:centrifugal,submersible'],
            'CAPACITY'             => ['sometimes', 'required', 'numeric', 'min:0'],
            'SALINITY'             => ['nullable', 'numeric', 'min:0'],
            // الطاقة والموقع
            'POWER_SOURCE'         => ['sometimes', 'required', 'string', 'in:electricity,solar'],
            'LATITUDE'             => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'LONGITUDE'            => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'IS_ACTIVE'            => ['boolean'],
            // بيانات خزان الوقود
            'TANK_SHAPE'           => ['nullable', 'string', 'max:100'],
            'TANK_MATERIAL'        => ['nullable', 'string', 'max:100'],
            'TANK_DIAMETER'        => ['nullable', 'numeric', 'min:0'],
            'TANK_HEIGHT'          => ['nullable', 'numeric', 'min:0'],
            // نظام الكلورة
            'CHLORINATION_SYSTEM'     => ['nullable', 'string', 'max:100'],
            'CHLORINE_TANK_CAPACITY'  => ['nullable', 'numeric', 'min:0'],
            'CHLORINE_TANK_TYPE'      => ['nullable', 'string', 'max:100'],
            'CHLORINE_TANK_MATERIAL'  => ['nullable', 'string', 'max:100'],
            'CHLORINE_PUMP_MODEL'     => ['nullable', 'string', 'max:100'],
            'CHLORINE_PUMP_TYPE'      => ['nullable', 'string', 'max:100'],
            // خزان التبريد
            'COOLING_TANK_CAPACITY'=> ['nullable', 'numeric', 'min:0'],
            'COOLING_TANK_TYPE'    => ['nullable', 'string', 'max:100'],
            'generators'           => ['nullable', 'array'],
            'generators.*'         => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'NAME.required'      => 'اسم البئر مطلوب',
            'PUMP_TYPE.in'       => 'نوع المضخة يجب أن يكون طرد مركزي أو غاطسة',
            'POWER_SOURCE.in'    => 'مصدر الطاقة يجب أن يكون كهرباء أو طاقة شمسية',
        ];
    }
}
