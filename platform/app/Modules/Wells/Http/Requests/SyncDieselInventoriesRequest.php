<?php

declare(strict_types=1);

namespace App\Modules\Wells\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncDieselInventoriesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $inventories = $this->input('inventories', []);
        if (! is_array($inventories)) {
            return;
        }

        $this->merge([
            'inventories' => array_map(
                fn (mixed $record): mixed => is_array($record)
                    ? array_change_key_case($record, CASE_UPPER)
                    : $record,
                $inventories,
            ),
        ]);
    }

    public function rules(): array
    {
        return [
            'inventories' => ['required', 'array', 'min:1', 'max:500'],
            'inventories.*.WELL_OID' => ['required', 'integer'],
            'inventories.*.INVENTORY_DATE' => ['required', 'date'],
            'inventories.*.GENERATOR_HOURS_READING' => ['required', 'numeric', 'min:0'],
            'inventories.*.DIESEL_HEIGHT_CM' => ['required', 'numeric', 'min:0'],
            'inventories.*.LOCAL_ID' => ['nullable', 'string', 'max:64', 'distinct'],
            'inventories.*.GENERATOR_OID' => ['nullable', 'integer'],
            'inventories.*.AVAILABLE_FUEL_LITERS' => ['nullable', 'numeric', 'min:0'],
            'inventories.*.LATITUDE' => ['nullable', 'numeric', 'between:-90,90'],
            'inventories.*.LONGITUDE' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
