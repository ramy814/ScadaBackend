<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Trip;
use App\Repositories\MunicipalityVehicleRepository;
use App\Services\TripSyncService;
use App\Services\VirtualFuelService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

class TripDataQualityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_filters_noise_jumps_and_tracking_gaps(): void
    {
        $start = new DateTimeImmutable('2026-07-19T08:00:00Z');
        $points = [
            $this->point(31.5000000, 34.4500000, 5, '08:00:00'),
            $this->point(31.5000400, 34.4500000, 5, '08:00:02'),
            $this->point(32.5000000, 34.4500000, 5, '08:00:04'),
            $this->point(31.6000000, 34.4500000, 5, '08:02:00'),
            $this->point(31.6009000, 34.4500000, 5, '08:02:10'),
        ];

        $metrics = (new VirtualFuelService)->metricsFromPoints(
            $points,
            $start,
            new DateTimeImmutable('2026-07-19T08:10:00Z'),
            true,
        );

        self::assertEqualsWithDelta(0.1, $metrics['distance_km'], 0.01);
        self::assertGreaterThan(100, $metrics['raw_distance_km']);
        self::assertContains('gps_noise', $metrics['quality_flags']);
        self::assertContains('gps_jump', $metrics['quality_flags']);
        self::assertContains('tracking_gap', $metrics['quality_flags']);
        self::assertCount(2, $metrics['accepted_segments']);
    }

    public function test_it_caps_duration_and_requires_exact_checksum_integrity(): void
    {
        $fuel = new VirtualFuelService;
        $metrics = $fuel->metricsFromPoints(
            [],
            new DateTimeImmutable('2026-07-19T00:00:00Z'),
            new DateTimeImmutable('2026-07-21T00:00:00Z'),
        );
        self::assertSame(720, $metrics['duration_minutes']);
        self::assertContains('duration_over_12h', $metrics['quality_flags']);

        $sync = new TripSyncService(
            Mockery::mock(MunicipalityVehicleRepository::class),
            $fuel,
        );
        $checksum = str_repeat('a', 64);
        self::assertTrue($sync->isCompleteUpload(2, 2, $checksum, $checksum));
        self::assertFalse($sync->isCompleteUpload(2, 3, $checksum, $checksum));
        self::assertFalse(
            $sync->isCompleteUpload(2, 2, str_repeat('b', 64), $checksum),
        );
    }

    public function test_it_does_not_turn_low_accuracy_jitter_into_distance(): void
    {
        $points = [
            $this->point(31.5000000, 34.4500000, 50, '08:00:00'),
            $this->point(31.5007000, 34.4500000, 50, '08:00:02'),
            $this->point(31.4993000, 34.4500000, 50, '08:00:04'),
        ];

        $metrics = (new VirtualFuelService)->metricsFromPoints(
            $points,
            new DateTimeImmutable('2026-07-19T08:00:00Z'),
            new DateTimeImmutable('2026-07-19T08:01:00Z'),
        );

        self::assertSame(0.0, $metrics['distance_km']);
        self::assertContains('gps_noise', $metrics['quality_flags']);
    }

    public function test_stationary_heartbeats_do_not_create_a_tracking_gap(): void
    {
        $points = [
            $this->point(31.5000000, 34.4500000, 5, '08:00:00'),
            $this->point(31.5000100, 34.4500000, 5, '08:00:30'),
            $this->point(31.5000000, 34.4500000, 5, '08:01:00'),
            $this->point(31.5000100, 34.4500000, 5, '08:01:30'),
            $this->point(31.5000000, 34.4500000, 5, '08:02:00'),
        ];

        $metrics = (new VirtualFuelService)->metricsFromPoints(
            $points,
            new DateTimeImmutable('2026-07-19T08:00:00Z'),
            new DateTimeImmutable('2026-07-19T08:02:00Z'),
        );

        self::assertSame(0.0, $metrics['distance_km']);
        self::assertContains('gps_noise', $metrics['quality_flags']);
        self::assertNotContains('tracking_gap', $metrics['quality_flags']);
        self::assertNotContains('tracking_coverage_gap', $metrics['quality_flags']);
    }

    public function test_it_withholds_fuel_for_a_long_tail_coverage_gap(): void
    {
        $fuel = new VirtualFuelService;
        $trip = new Trip;
        $metrics = $fuel->metricsFromPoints(
            [
                $this->point(31.5000000, 34.4500000, 5, '08:00:00'),
                $this->point(31.5009000, 34.4500000, 5, '08:00:10'),
            ],
            new DateTimeImmutable('2026-07-19T08:00:00Z'),
            new DateTimeImmutable('2026-07-19T08:25:00Z'),
        );

        self::assertContains('tracking_gap', $metrics['quality_flags']);
        self::assertContains('tracking_coverage_gap', $metrics['quality_flags']);
        self::assertNull($fuel->estimateFromMetrics($trip, $metrics));
    }

    public function test_it_withholds_fuel_for_a_twenty_minute_internal_gap(): void
    {
        $fuel = new VirtualFuelService;
        $trip = new Trip;
        $metrics = $fuel->metricsFromPoints(
            [
                $this->point(31.5000000, 34.4500000, 5, '08:00:00'),
                $this->point(31.5009000, 34.4500000, 5, '08:20:00'),
            ],
            new DateTimeImmutable('2026-07-19T08:00:00Z'),
            new DateTimeImmutable('2026-07-19T08:20:00Z'),
        );

        self::assertContains('tracking_gap', $metrics['quality_flags']);
        self::assertContains('tracking_coverage_gap', $metrics['quality_flags']);
        self::assertNull($fuel->estimateFromMetrics($trip, $metrics));
    }

    public function test_it_matches_the_mobile_canonical_checksum_fixture(): void
    {
        $sync = new TripSyncService(
            Mockery::mock(MunicipalityVehicleRepository::class),
            new VirtualFuelService,
        );
        $points = [
            [
                'sequence_number' => 1,
                'latitude' => '31.5000000',
                'longitude' => '34.4500000',
                'accuracy' => '5.12',
                'speed' => '0.00',
                'captured_at' => '2026-07-19T08:00:00Z',
                'recorded_at' => '2026-07-19T08:00:01Z',
                'client_is_valid' => true,
                'quality_reason' => null,
            ],
            [
                'sequence_number' => 2,
                'latitude' => '31.5007000',
                'longitude' => '34.4500000',
                'accuracy' => '50.00',
                'speed' => '0.00',
                'captured_at' => '2026-07-19T08:00:02Z',
                'recorded_at' => '2026-07-19T08:00:02Z',
                'client_is_valid' => false,
                'quality_reason' => 'within_gps_noise',
            ],
        ];

        self::assertSame(
            '4b5e8bb8f29ad393b9789b3fdbda7e3f0abe555bf2b17fd2852790d6552910a0',
            $sync->checksumForPoints($points),
        );
    }

    public function test_it_retains_but_excludes_client_rejected_samples(): void
    {
        $points = [[
            'latitude' => 31.5,
            'longitude' => 34.45,
            'accuracy' => 5,
            'speed' => 0,
            'captured_at' => new DateTimeImmutable('2026-07-19T08:00:00Z'),
            'recorded_at' => new DateTimeImmutable('2026-07-19T08:00:00Z'),
            'client_is_valid' => false,
            'quality_reason' => 'mocked_location',
        ]];

        $metrics = (new VirtualFuelService)->metricsFromPoints(
            $points,
            new DateTimeImmutable('2026-07-19T08:00:00Z'),
            new DateTimeImmutable('2026-07-19T08:01:00Z'),
        );

        self::assertSame(0.0, $metrics['distance_km']);
        self::assertContains('mocked_location', $metrics['quality_flags']);
        self::assertContains('no_valid_points', $metrics['quality_flags']);
    }

    public function test_it_withholds_fuel_for_policy_distance_and_ignores_untrusted_idle_time(): void
    {
        $fuel = new VirtualFuelService;
        $trip = new Trip(['wait_minutes' => 60]);
        $trip->setRelation('stops', new Collection([
            (object) [
                'started_at' => new CarbonImmutable('2026-07-19T08:00:00Z'),
                'ended_at' => new CarbonImmutable('2026-07-19T10:00:00Z'),
            ],
        ]));

        self::assertSame(1.2, $fuel->estimateFromMetrics($trip, [
            'distance_km' => 10.0,
            'duration_minutes' => 120,
            'quality_flags' => [],
        ]));
        self::assertNull($fuel->estimateFromMetrics($trip, [
            'distance_km' => 201.0,
            'duration_minutes' => 120,
            'quality_flags' => ['distance_over_policy'],
        ]));
        self::assertNull($fuel->estimateFromMetrics($trip, [
            'distance_km' => 10.0,
            'duration_minutes' => 720,
            'quality_flags' => ['duration_over_12h'],
        ]));
    }

    private function point(
        float $latitude,
        float $longitude,
        float $accuracy,
        string $time,
    ): array {
        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'speed' => 0,
            'captured_at' => new DateTimeImmutable("2026-07-19T{$time}Z"),
        ];
    }
}
