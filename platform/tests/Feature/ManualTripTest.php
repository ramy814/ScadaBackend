<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualTripTest extends TestCase
{
    private function catalogs(): void
    {
        foreach (['Depot', 'Station'] as $name) {
            $this->db()->table('mission_locations')->insert(['name' => $name, 'category' => 'both', 'active' => 1]);
        }
        $this->db()->table('mission_types')->insert(['name' => 'Delivery', 'active' => 1]);
        $this->db()->table('g_store.beneficiaries')->insert(['oid' => 500, 'full_name' => 'Driver A', 'mobile_num' => '0599']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'oracle_vehicle_id' => 700, 'started_at' => '2026-10-01T08:00:00', 'ended_at' => '2026-10-01T09:30:00',
            'origin_text' => 'Depot', 'destination_text' => 'Station', 'mission_type' => 'Delivery',
            'start_odometer' => 1000, 'end_odometer' => 1042, 'estimated_distance_km' => 999, ...$overrides,
        ];
    }

    public function test_standalone_assistant_lands_on_register_without_fleet_dashboard(): void
    {
        $this->catalogs();
        $this->actingAsUser(8); // no SYSTEM_PERMISSIONS rows at all
        $this->stationVehicle(8, 700, 1);
        $this->db()->table('g_store.vehicle')->where('oid', 700)->update(['beneficiary_oid' => 500]);

        $this->get('/')->assertRedirect('/fleet/manual');
        $this->get('/fleet/manual')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Fleet/ManualTrips')->where('can.enter', true)->where('can.approve', false)
            ->where('shell.applications.0.key', 'fleet')->where('shell.applications.0.home', route('fleet.manual')));
        $this->get('/fleet/reviews')->assertForbidden();
        $this->get('/fleet/manual/requests')->assertForbidden();
    }

    public function test_draft_uses_odometer_distance_then_submit_locks_it_and_approver_approves(): void
    {
        $this->catalogs();
        $this->actingAsUser(8);
        $this->stationVehicle(8, 700, 3);
        $this->db()->table('g_store.vehicle')->where('oid', 700)->update(['beneficiary_oid' => 500]);

        $this->post('/fleet/manual', $this->payload())->assertSessionHasNoErrors()->assertRedirect('/fleet/manual');
        $trip = $this->db()->table('trips')->where('entry_source', 'manual')->first();
        $this->assertSame('draft', $trip->review_status);
        $this->assertEquals(42.0, (float) $trip->distance_km); // server recalculates from odometers, ignores the forged estimate
        $this->assertSame('Driver A', $trip->driver_name);

        $this->post("/fleet/manual/{$trip->id}/submit")->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->db()->table('trips')->where('id', $trip->id)->value('review_status'));
        $this->put("/fleet/manual/{$trip->id}", $this->payload())->assertNotFound(); // no longer editable

        $this->post("/fleet/manual/{$trip->id}/approve", ['note' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->db()->table('trips')->where('id', $trip->id)->value('review_status'));
        $this->assertSame(['trip.manual.created', 'trip.manual.submitted', 'trip.reviewed'], $this->db()->table('audit_logs')->where('entity_id', $trip->id)->orderBy('id')->pluck('event_type')->all());
    }

    public function test_validation_rejects_cross_day_unknown_places_and_vehicles_outside_scope(): void
    {
        $this->catalogs();
        $this->actingAsUser(8);
        $this->stationVehicle(8, 700, 1);
        $this->db()->table('g_store.vehicle')->insert(['oid' => 701, 'plate_num' => 'P701', 'beneficiary_oid' => 500]);

        $this->post('/fleet/manual', $this->payload(['ended_at' => '2026-10-02T09:00:00']))->assertSessionHasErrors('ended_at');
        $this->post('/fleet/manual', $this->payload(['origin_text' => 'Nowhere']))->assertSessionHasErrors('origin_text');
        $this->post('/fleet/manual', $this->payload(['oracle_vehicle_id' => 701]))->assertSessionHasErrors('oracle_vehicle_id');
        $this->assertSame(0, $this->db()->table('trips')->count());
    }
}
