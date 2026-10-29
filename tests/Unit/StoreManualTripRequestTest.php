<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\StoreManualTripRequest;
use App\Repositories\TripRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StoreManualTripRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mission_locations', 'mission_types'] as $table) {
            Schema::create($table, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->integer('sort_order')->default(0);
                $table->boolean('active')->default(true);
            });
        }
        DB::table('mission_locations')->insert([
            ['name' => 'المحطة', 'active' => true], ['name' => 'البلدية', 'active' => true],
            ['name' => 'موقع معطل', 'active' => false],
        ]);
        DB::table('mission_types')->insert([
            ['name' => 'مهمة', 'active' => true], ['name' => 'مهمة إدارية', 'active' => true],
            ['name' => 'مهمة معطلة', 'active' => false],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('mission_types');
        Schema::dropIfExists('mission_locations');

        parent::tearDown();
    }

    public function test_free_text_and_inactive_options_are_rejected_on_create_and_update(): void
    {
        foreach (['POST', 'PUT'] as $method) {
            foreach (['origin_text' => 'موقع معطل', 'destination_text' => 'موقع معطل', 'mission_type' => 'مهمة معطلة'] as $field => $inactive) {
                foreach (['نص غير مسجل', $inactive] as $value) {
                    try {
                        $this->resolvedRequest([$field => $value, 'estimated_distance_km' => 10], $method);
                        self::fail('Only active dropdown options may be saved.');
                    } catch (ValidationException $exception) {
                        self::assertArrayHasKey($field, $exception->errors());
                    }
                }
            }
        }
    }

    public function test_odometer_distance_overrides_an_estimate_and_supports_zero_readings(): void
    {
        foreach ([[1000, 1025, 25], [0, 25, 25], [1000, 1000, 0]] as [$start, $end, $distance]) {
            $request = $this->resolvedRequest([
                'start_odometer' => $start, 'end_odometer' => $end,
                'estimated_distance_km' => 'ignored',
            ]);
            self::assertSame($distance, $request->validated('estimated_distance_km'));
        }
        self::assertSame(25, $this->resolvedRequest([
            'start_odometer' => 1000, 'end_odometer' => 1025,
        ])->validated('estimated_distance_km'));
    }

    public function test_manual_estimate_remains_available_without_both_readings(): void
    {
        foreach ([[], ['start_odometer' => 1000], ['end_odometer' => 1025]] as $readings) {
            self::assertSame(12.75, $this->resolvedRequest([
                ...$readings, 'estimated_distance_km' => 12.75,
            ])->validated('estimated_distance_km'));
        }
    }

    public function test_reversed_odometer_readings_are_rejected(): void
    {
        try {
            $this->resolvedRequest(['start_odometer' => 1000, 'end_odometer' => 999]);
            self::fail('A reversed odometer must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('end_odometer', $exception->errors());
        }
    }

    public function test_an_estimate_is_required_when_readings_are_incomplete(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolvedRequest(['start_odometer' => 1000]);
    }

    private function resolvedRequest(array $data, string $method = 'POST'): StoreManualTripRequest
    {
        $request = StoreManualTripRequest::create('/', $method, [
            'oracle_vehicle_id' => 1,
            'started_at' => '2026-10-12 10:00:00',
            'ended_at' => '2026-10-12 11:00:00',
            'origin_text' => 'المحطة', 'destination_text' => 'البلدية', 'mission_type' => 'مهمة',
            ...$data,
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request;
    }

    public function test_it_accepts_a_later_end_time_on_the_same_day(): void
    {
        $validator = $this->validator('2026-10-12 10:00:00', '2026-10-12 23:59:59');

        self::assertFalse($validator->fails());
    }

    public function test_it_rejects_an_equal_or_earlier_end_time(): void
    {
        $validator = $this->validator('2026-10-12 10:00:00', '2026-10-12 10:00:00');

        self::assertTrue($validator->fails());
        self::assertSame(
            'وقت انتهاء الرحلة يجب أن يكون أكبر من وقت البداية.',
            $validator->errors()->first('ended_at'),
        );
    }

    public function test_it_rejects_a_trip_spanning_two_days(): void
    {
        $validator = $this->validator('2026-10-12 23:00:00', '2026-10-13 01:00:00');

        self::assertTrue($validator->fails());
        self::assertSame(
            'يجب أن تكون بداية الرحلة ونهايتها في اليوم نفسه.',
            $validator->errors()->first('ended_at'),
        );
    }

    private function validator(string $startedAt, string $endedAt): \Illuminate\Contracts\Validation\Validator
    {
        $request = StoreManualTripRequest::create('/', 'POST', [
            'oracle_vehicle_id' => 1,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'origin_text' => 'المحطة',
            'destination_text' => 'البلدية',
            'mission_type' => 'مهمة إدارية',
            'estimated_distance_km' => 10,
        ]);
        $validator = Validator::make($request->all(), $request->rules(app(TripRepository::class)), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}
