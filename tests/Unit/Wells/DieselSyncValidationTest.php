<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Http\Controllers\Api\Wells\SyncController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DieselSyncValidationTest extends TestCase
{
    public function test_generator_reading_and_diesel_height_are_required(): void
    {
        $controller = app(SyncController::class);
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

            $request = Request::create(
                '/api/wells/diesel-inventories/sync',
                'POST',
                ['inventories' => [$inventory]],
            );

            try {
                $controller->syncDiesel($request);
                self::fail("Validation accepted missing {$field}.");
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($missingField, $exception->errors());
            }
        }
    }
}
