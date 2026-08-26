<?php

declare(strict_types=1);

namespace Tests\Unit\Wells;

use App\Http\Requests\Wells\SyncDieselInventoriesRequest;
use App\Http\Requests\Wells\SyncNotificationsRequest;
use App\Http\Requests\Wells\SyncReadingsRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SyncBatchRequestTest extends TestCase
{
    #[DataProvider('batchRequests')]
    public function test_sync_payloads_are_limited_to_500_records(
        FormRequest $request,
        string $root,
        array $record,
    ): void {
        $validator = Validator::make(
            [$root => array_fill(0, 501, $record)],
            $request->rules(),
        );

        self::assertTrue($validator->fails());
        self::assertArrayHasKey($root, $validator->errors()->toArray());
    }

    public function test_duplicate_client_ids_are_rejected_inside_one_batch(): void
    {
        $record = [
            'LOCAL_ID' => 'same-local-id',
            'WELL_OID' => 1,
            'READING_DATE' => '2026-08-26 10:00:00',
            'GENERATOR_HOURS_READING' => 120.5,
        ];
        $request = new SyncReadingsRequest;
        $validator = Validator::make(
            ['readings' => [$record, $record]],
            $request->rules(),
        );

        self::assertTrue($validator->fails());
        self::assertArrayHasKey('readings.0.LOCAL_ID', $validator->errors()->toArray());
        self::assertArrayHasKey('readings.1.LOCAL_ID', $validator->errors()->toArray());
    }

    public function test_legacy_lowercase_reading_keys_are_normalized(): void
    {
        $request = SyncReadingsRequest::create('/', 'POST', [
            'readings' => [[
                'local_id' => 'legacy-reading',
                'well_oid' => 1,
                'reading_date' => '2026-08-26 10:00:00',
                'flow_rate' => 120.5,
            ]],
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();

        self::assertSame('legacy-reading', $request->validated('readings.0.LOCAL_ID'));
        self::assertSame(1, $request->validated('readings.0.WELL_OID'));
        self::assertSame(120.5, $request->validated('readings.0.FLOW_RATE'));
    }

    public static function batchRequests(): array
    {
        return [
            'readings' => [
                new SyncReadingsRequest,
                'readings',
                [
                    'WELL_OID' => 1,
                    'READING_DATE' => '2026-08-26 10:00:00',
                    'GENERATOR_HOURS_READING' => 120.5,
                ],
            ],
            'diesel inventories' => [
                new SyncDieselInventoriesRequest,
                'inventories',
                [
                    'WELL_OID' => 1,
                    'INVENTORY_DATE' => '2026-08-26',
                    'GENERATOR_HOURS_READING' => 120.5,
                    'DIESEL_HEIGHT_CM' => 45,
                ],
            ],
            'notifications' => [
                new SyncNotificationsRequest,
                'notifications',
                [
                    'WELL_OID' => 1,
                    'NOTIFICATION_TYPE_OID' => 2,
                    'MESSAGE' => 'test',
                ],
            ],
        ];
    }
}
