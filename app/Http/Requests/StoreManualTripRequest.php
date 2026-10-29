<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Repositories\TripRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreManualTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $start = filter_var($this->input('start_odometer'), FILTER_VALIDATE_INT);
        $end = filter_var($this->input('end_odometer'), FILTER_VALIDATE_INT);

        if ($start !== false && $end !== false) {
            $this->merge(['estimated_distance_km' => $end - $start]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(TripRepository $trips): array
    {
        $options = $trips->manualTripOptions();

        return [
            'oracle_vehicle_id' => ['required', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'origin_text' => ['required', 'string', 'max:255', Rule::in($options['locations'])],
            'destination_text' => ['required', 'string', 'max:255', Rule::in($options['locations'])],
            'mission_type' => ['required', 'string', 'max:255', Rule::in($options['mission_types'])],
            'estimated_distance_km' => ['required', 'numeric', 'min:0', 'max:99999.999', 'decimal:0,3'],
            'start_odometer' => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'end_odometer' => ['nullable', 'integer', 'min:0', 'max:999999999', ...($this->filled('start_odometer') ? ['gte:start_odometer'] : [])],
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
            'origin_text.in' => 'اختر مكان الانطلاق من القائمة فقط.',
            'destination_text.in' => 'اختر الوجهة من القائمة فقط.',
            'mission_type.in' => 'اختر نوع المهمة من القائمة فقط.',
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
