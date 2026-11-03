<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The trip page receives the same figures the legacy TripShow page shows. */
class TripDetailParityTest extends TestCase
{
    public function test_trip_page_exposes_recorded_and_filtered_distance_and_the_gap_estimate(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $start = CarbonImmutable::parse('2026-07-19 08:00:00');
        $trip = $this->trip(['quality_version' => 2, 'started_at' => $start, 'ended_at' => $start->addMinutes(4), 'points_received' => 4, 'points_expected' => 4, 'wait_minutes' => 3]);

        // Two moving samples, a 3-minute silence, then two more samples (legacy gap fixture shape).
        $samples = [[31.5000, '08:00:00'], [31.5009, '08:00:10'], [31.5040, '08:03:10'], [31.5049, '08:03:20']];
        foreach ($samples as $i => [$lat, $time]) {
            $at = CarbonImmutable::parse("2026-07-19 {$time}");
            $this->db()->table('trip_points')->insert(['trip_id' => $trip, 'sequence_number' => $i + 1, 'latitude' => $lat, 'longitude' => 34.45, 'accuracy' => 5, 'speed' => 10, 'captured_at' => $at, 'recorded_at' => $at]);
        }
        $this->db()->table('trip_tracking_events')->insert(['trip_id' => $trip, 'event_type' => 'tracking_gap', 'detected_at' => $start->addMinute(), 'details_json' => json_encode(['reason' => 'provider_silent', 'silence_seconds' => 180, 'device' => 'x'])]);
        $this->db()->table('trip_passengers')->insert(['trip_id' => $trip, 'name' => 'Passenger', 'employee_id' => '77', 'source' => 'employee_list']);

        $this->get("/fleet/trips/{$trip}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Fleet/Trip')
            ->where('metrics.quality_version', 2)
            ->where('metrics.filtered', true)
            ->where('metrics.points_count', 4)
            ->has('metrics.recorded_distance_km')
            ->has('metrics.distance_km')
            ->has('metrics.reported_duration_minutes')
            ->has('metrics.collapsed_points')
            ->has('metrics.recorded_start.lat')
            ->has('metrics.recorded_end.lat')
            ->where('metrics.fuel_rate_per_km', fn ($rate) => $rate > 0)
            ->where('metrics.gap_estimate.estimated', 1)
            ->where('metrics.gap_estimate.distance_km', fn ($km) => $km > 0)
            ->where('metrics.gap_estimate.distance_with_gaps_km', fn ($km) => $km > 0)
            ->has('metrics.gaps', 1)
            ->where('trip.total_wait_minutes', 3)
            ->where('trip.events.0.details', ['reason' => 'provider_silent', 'silence_seconds' => 180])
            ->where('trip.passengers.0.source', 'employee_list'));
    }
}
