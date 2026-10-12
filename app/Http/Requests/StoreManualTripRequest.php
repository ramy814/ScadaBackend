<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreManualTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'oracle_vehicle_id' => ['required', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'origin_text' => ['required', 'string', 'max:255'],
            'destination_text' => ['required', 'string', 'max:255'],
            'mission_type' => ['required', 'string', 'max:255'],
            'estimated_distance_km' => ['required', 'numeric', 'min:0', 'max:99999.999', 'decimal:0,3'],
            'start_odometer' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'end_odometer' => ['nullable', 'integer', 'min:0', 'max:999999999', 'gte:start_odometer'],
            'wait_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'oracle_vehicle_id.required' => 'اختر المركبة.',
            'started_at.required' => 'وقت بدء الرحلة مطلوب.',
            'ended_at.required' => 'وقت انتهاء الرحلة مطلوب.',
            'ended_at.after' => 'وقت انتهاء الرحلة يجب أن يكون أكبر من وقت البداية.',
            'origin_text.required' => 'مكان الانطلاق مطلوب.',
            'destination_text.required' => 'الوجهة مطلوبة.',
            'mission_type.required' => 'نوع المهمة مطلوب.',
            'estimated_distance_km.required' => 'المسافة التقديرية مطلوبة.',
            'estimated_distance_km.numeric' => 'المسافة التقديرية يجب أن تكون رقماً.',
            'estimated_distance_km.decimal' => 'المسافة التقديرية تقبل حتى ثلاث خانات عشرية.',
            'end_odometer.gte' => 'قراءة العداد عند الانتهاء يجب ألا تقل عن قراءة البدء.',
            '*.integer' => 'يجب إدخال رقم صحيح.',
            '*.max' => 'القيمة المدخلة أكبر من الحد المسموح.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['started_at', 'ended_at'])) {
                return;
            }

            $startedAt = CarbonImmutable::parse((string) $this->input('started_at'));
            $endedAt = CarbonImmutable::parse((string) $this->input('ended_at'));

            if (! $startedAt->isSameDay($endedAt)) {
                $validator->errors()->add('ended_at', 'يجب أن تكون بداية الرحلة ونهايتها في اليوم نفسه.');
            }
        });
    }
}
