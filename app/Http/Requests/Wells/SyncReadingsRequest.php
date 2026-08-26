<?php

declare(strict_types=1);

namespace App\Http\Requests\Wells;

use Illuminate\Foundation\Http\FormRequest;

class SyncReadingsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $readings = $this->input('readings', []);
        if (! is_array($readings)) {
            return;
        }

        $this->merge([
            'readings' => array_map(
                fn (mixed $record): mixed => is_array($record)
                    ? array_change_key_case($record, CASE_UPPER)
                    : $record,
                $readings,
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'readings' => ['required', 'array', 'min:1', 'max:500'],
            'readings.*.LOCAL_ID' => ['nullable', 'string', 'max:64', 'distinct'],
            'readings.*.WELL_OID' => ['required', 'integer'],
            'readings.*.READING_DATE' => ['required', 'date'],
            'readings.*.GENERATOR_HOURS_READING' => [
                'nullable',
                'required_without:readings.*.FLOW_RATE',
                'numeric',
            ],
            'readings.*.FLOW_RATE' => [
                'nullable',
                'required_without:readings.*.GENERATOR_HOURS_READING',
                'numeric',
            ],
            'readings.*.PUMP_HOURS_READING' => ['nullable', 'numeric'],
            'readings.*.LATITUDE' => ['nullable', 'numeric', 'between:-90,90'],
            'readings.*.LONGITUDE' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
