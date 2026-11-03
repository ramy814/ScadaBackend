<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsAndOverviewTest extends TestCase
{
    public function test_overview_reports_actionable_items_per_granted_application(): void
    {
        $this->actingAsUser(5, ['fleet', 'valves']);
        $this->stationVehicle(5, 700, 2);
        $this->trip();
        $this->trip(['review_status' => 'approved']);
        $this->db()->table('valve_maintenance_reports')->insert(['valve_oid' => 1, 'status' => 'pending', 'created_at' => now()]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Overview')
            ->has('applications', 2)
            ->where('applications.0.key', 'fleet')
            ->where('applications.0.attention.0.key', 'fleet.awaiting')
            ->where('applications.0.attention.0.count', 1)
            ->where('applications.1.attention.0.key', 'valves.pending_maintenance'));
    }

    public function test_suspension_blocks_workspaces_but_keeps_the_recovery_page(): void
    {
        $this->actingAsUser(1, ['fleet']);

        $this->put('/platform/operations', ['shutdown_enabled' => true, 'reason' => 'صيانة', 'confirmation' => '1'])->assertSessionHasNoErrors();
        $this->get('/fleet/reviews')->assertStatus(503)->assertInertia(fn (Assert $page) => $page->component('Errors/Suspended')->where('canReopen', true));
        $this->get('/platform/operations')->assertOk();

        $this->put('/platform/operations', ['shutdown_enabled' => false])->assertSessionHasNoErrors();
        $this->get('/fleet/reviews')->assertOk();
    }

    public function test_suspension_requires_reason_and_acknowledgement(): void
    {
        $this->actingAsUser(1, ['fleet']);

        $this->put('/platform/operations', ['shutdown_enabled' => true])->assertSessionHasErrors(['reason', 'confirmation']);
        $this->assertSame(0, $this->db()->table('system_operational_states')->count());
    }
}
