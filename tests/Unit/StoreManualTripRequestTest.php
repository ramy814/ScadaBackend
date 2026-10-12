<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\StoreManualTripRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreManualTripRequestTest extends TestCase
{
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
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }
}
