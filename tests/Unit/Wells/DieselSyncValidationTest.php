<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Http\Requests\Wells\SyncDieselInventoriesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DieselSyncValidationTest extends TestCase
{
    public function test_generator_reading_and_diesel_height_are_required(): void
    {
        $request = new SyncDieselInventoriesRequest;
        $baseInventory = [
            'WELL_OID' => 1,
            'INVENTORY_DATE' => '2026-07-23',
            'GENERATOR_HOURS_READING' => 125.5,
            'DIESEL_HEIGHT_CM' => 48,
        ];

        foreach ([
            'inventories.0.GENERATOR_HOURS_READING',
            'inventories.0.DIESEL_HEIGHT_CM',
        ] as $missingField) {
            $field = str($missingField)->afterLast('.')->toString();
            $inventory = $baseInventory;
            unset($inventory[$field]);

            $validator = Validator::make(
                ['inventories' => [$inventory]],
                $request->rules(),
            );

            self::assertTrue($validator->fails());
            self::assertArrayHasKey($missingField, $validator->errors()->toArray());
        }
    }
}
