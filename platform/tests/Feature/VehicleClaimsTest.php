<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Fleet\Repositories\MunicipalityVehicleRepository;
use App\Modules\Fleet\Repositories\TripRepository;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Vehicle financial claims and the hourly-price snapshot (legacy commit 72f4cfe). */
class VehicleClaimsTest extends TestCase
{
    public function test_platform_approval_freezes_the_vehicle_hourly_price_and_other_decisions_clear_it(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 2);
        $this->db()->table('g_store.vehicle')->where('oid', 700)->update(['hourly_price' => 42.5]);
        $approved = $this->trip();
        $rejected = $this->trip(['hourly_price' => 30]);

        $this->post("/fleet/trips/{$approved}/approve")->assertSessionHasNoErrors();
        $this->post("/fleet/trips/{$rejected}/reject", ['note' => 'No'])->assertSessionHasNoErrors();

        $this->assertEquals(42.5, $this->db()->table('trips')->where('id', $approved)->value('hourly_price'));
        $this->assertNull($this->db()->table('trips')->where('id', $rejected)->value('hourly_price'));
        $audit = json_decode((string) $this->db()->table('audit_logs')->where('entity_id', $approved)->value('payload'), true);
        $this->assertSame(42.5, $audit['hourly_price']);
    }

    public function test_manual_approval_stores_the_hourly_price_captured_at_approval(): void
    {
        $trip = $this->trip(['entry_source' => 'manual', 'points_received' => 0]);

        $approved = app(TripRepository::class)->approveManual($trip, 140, 'Reviewer', null, 37.5);

        $this->assertSame('37.50', $approved->hourly_price);
        $audit = json_decode((string) $this->db()->table('audit_logs')->where('entity_id', $trip)->value('payload'), true);
        $this->assertSame(37.5, $audit['hourly_price']);
    }

    public function test_claim_trips_are_approved_finished_in_range_and_scoped(): void
    {
        $at = fn (string $value) => CarbonImmutable::parse($value);
        $ids = [
            $this->trip(['oracle_vehicle_id' => '10', 'review_status' => 'approved', 'started_at' => $at('2026-11-03 08:00'), 'ended_at' => $at('2026-11-03 10:00'), 'hourly_price' => 30]),
            $this->trip(['oracle_vehicle_id' => '10', 'review_status' => 'pending', 'started_at' => $at('2026-11-03 11:00'), 'ended_at' => $at('2026-11-03 12:00')]),
            $this->trip(['oracle_vehicle_id' => '10', 'review_status' => 'approved', 'started_at' => $at('2026-10-31 08:00'), 'ended_at' => $at('2026-10-31 09:00'), 'hourly_price' => 30]),
            $this->trip(['oracle_vehicle_id' => '20', 'entry_source' => 'manual', 'review_status' => 'approved', 'started_at' => $at('2026-11-04 08:00'), 'ended_at' => $at('2026-11-04 09:00'), 'hourly_price' => 25]),
            $this->trip(['oracle_vehicle_id' => '30', 'entry_source' => 'manual', 'review_status' => 'approved', 'started_at' => $at('2026-11-05 08:00'), 'ended_at' => $at('2026-11-05 09:00'), 'hourly_price' => 25]),
            $this->trip(['oracle_vehicle_id' => '10', 'review_status' => 'approved', 'status' => 'uploaded', 'started_at' => $at('2026-11-06 08:00'), 'ended_at' => null, 'hourly_price' => 30]),
        ];
        $repository = app(TripRepository::class);
        $from = $at('2026-11-01')->startOfDay();
        $to = $at('2026-11-30')->endOfDay();

        $this->assertSame([$ids[0], $ids[3]], $repository->approvedTripsForClaims($from, $to, '', ['20'])->pluck('id')->all());
        $this->assertSame([$ids[3]], $repository->approvedTripsForClaims($from, $to, '20', ['20'])->pluck('id')->all());
        $this->assertSame([$ids[0]], $repository->approvedTripsForClaims($from, $to, '', [])->pluck('id')->all());
    }

    public function test_hourly_prices_are_read_from_the_vehicle_table_keyed_by_oid(): void
    {
        $this->db()->table('g_store.vehicle')->insert([
            ['oid' => 10, 'hourly_price' => 35.5], ['oid' => 20, 'hourly_price' => null], ['oid' => 30, 'hourly_price' => 50],
        ]);
        $repository = app(MunicipalityVehicleRepository::class);

        $this->assertSame(['10' => 35.5, '20' => null], $repository->hourlyPricesFor(['10', 20, '', null]));
        $this->assertSame(50.0, $repository->hourlyPriceFor(30));
        $this->assertNull($repository->hourlyPriceFor(null));
        $this->assertNull($repository->hourlyPriceFor(99));
    }

    public function test_claims_screen_and_exports_for_fleet_users_only(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->db()->table('g_store.vehicle')->insert(['oid' => 700, 'plate_num' => '3-1234', 'hourly_price' => 40]);
        $this->trip(['review_status' => 'approved', 'started_at' => CarbonImmutable::parse('2026-11-03 08:00'), 'ended_at' => CarbonImmutable::parse('2026-11-03 09:30'), 'hourly_price' => 40]);

        $this->get('/fleet/claims?month=2026-11')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Fleet/Claims')
            ->where('filters.month', '2026-11')
            ->where('totals.vehicles', 1)
            ->where('totals.work_time', '1:30')
            ->where('totals.amount', 60)
            ->where('rows.0.days.0.date', '2026-11-03')
            ->where('shell.navigation', fn ($groups) => collect($groups)->flatMap(fn ($g) => $g['items'])->contains('key', 'claims')));

        $this->get('/fleet/claims/csv?month=2026-11')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->get('/fleet/claims/pdf?month=2026-11')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/fleet/claims?date_from=2026-11-10&date_to=2026-11-01')->assertSessionHasErrors('date_to');

        $this->actingAsUser(6, ['wells']);
        $this->get('/fleet/claims')->assertForbidden();
    }
}
