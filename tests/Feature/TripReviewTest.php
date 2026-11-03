<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TripReviewTest extends TestCase
{
    public function test_reviewer_in_station_scope_approves_once_with_an_audit_row(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 2);
        $trip = $this->trip();

        $this->post("/fleet/trips/{$trip}/approve", ['note' => 'OK'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->db()->table('trips')->where('id', $trip)->value('review_status'));
        $this->assertSame(1, $this->db()->table('audit_logs')->where('entity_id', $trip)->where('event_type', 'trip.reviewed')->count());

        // Documented immutability: a final decision can never be changed (the legacy controller allowed it).
        $this->post("/fleet/trips/{$trip}/reject")->assertSessionHasErrors('decision');
        $this->assertSame('approved', $this->db()->table('trips')->where('id', $trip)->value('review_status'));
        $this->assertSame(1, $this->db()->table('audit_logs')->where('entity_id', $trip)->count());
    }

    public function test_decision_outside_station_scope_or_on_removed_station_is_forbidden(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 2, removedBy: 'someone');
        $this->stationVehicle(5, 701, 1); // entry-only scope does not grant review
        $removed = $this->trip(['oracle_vehicle_id' => '700']);
        $entryOnly = $this->trip(['oracle_vehicle_id' => '701']);

        $this->post("/fleet/trips/{$removed}/approve")->assertForbidden();
        $this->post("/fleet/trips/{$entryOnly}/approve")->assertForbidden();
        $this->assertSame(0, $this->db()->table('audit_logs')->count());
    }

    public function test_unfinished_trips_and_manual_clarifications_are_rejected(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 3);
        $active = $this->trip(['status' => 'uploaded', 'ended_at' => null]);
        $manual = $this->trip(['entry_source' => 'manual', 'points_received' => 0]);

        $this->post("/fleet/trips/{$active}/approve")->assertSessionHasErrors('decision');
        $this->post("/fleet/trips/{$manual}/clarify", ['note' => 'Why?'])->assertSessionHasErrors('decision');
        $this->post("/fleet/trips/{$manual}/clarify")->assertSessionHasErrors('note');
    }

    public function test_final_decision_after_driver_answer_keeps_the_question(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 2);
        $trip = $this->trip(['review_status' => 'needs_clarification', 'review_note' => 'Route?', 'clarification_response' => 'Road closed']);

        $this->post("/fleet/trips/{$trip}/approve", ['note' => 'Accepted'])->assertSessionHasNoErrors();
        $this->assertSame("الإجراء النهائي: Accepted\n---\nسؤال التوضيح: Route?", $this->db()->table('trips')->where('id', $trip)->value('review_note'));
    }

    public function test_queue_hides_drafts_out_of_scope_manual_rows_and_experimental_trips(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $this->stationVehicle(5, 700, 2);
        $visible = $this->trip();
        $this->trip(['points_received' => 12]); // experimental
        $manualInScope = $this->trip(['entry_source' => 'manual', 'points_received' => 0]);
        $this->trip(['entry_source' => 'manual', 'review_status' => 'draft']);
        $this->trip(['entry_source' => 'manual', 'oracle_vehicle_id' => '999']);
        $oldPending = $this->trip(['started_at' => now()->subMonths(4), 'ended_at' => now()->subMonths(4)->addHour()]);

        $this->get('/fleet/reviews')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Fleet/Reviews')
            ->where('trips.total', 2) // decision queue ignores the period: the 4-month-old pending trip is included
            ->where('counts.mobile.awaiting', 2)
            ->where('counts.manual.awaiting', 1));

        $this->get('/fleet/reviews?source=manual')->assertInertia(fn (Assert $page) => $page
            ->where('trips.total', 1)->where('trips.data.0.id', $manualInScope));
        $this->get('/fleet/reviews?experimental=1')->assertInertia(fn (Assert $page) => $page->where('trips.total', 3));
        $this->assertNotNull($visible + $oldPending);
    }

    public function test_drawer_json_and_mobile_trip_view_without_review_scope(): void
    {
        $this->actingAsUser(5, ['fleet']);
        $trip = $this->trip();
        $this->db()->table('trip_points')->insert(['trip_id' => $trip, 'sequence_number' => 1, 'latitude' => 31.5, 'longitude' => 34.46, 'client_is_valid' => 1]);

        $this->getJson("/fleet/trips/{$trip}")->assertOk()
            ->assertJsonPath('trip.id', $trip)
            ->assertJsonPath('can.review', false); // base fleet access may view app trips, not decide

        $manual = $this->trip(['entry_source' => 'manual', 'oracle_vehicle_id' => '999']);
        $this->getJson("/fleet/trips/{$manual}")->assertForbidden();
    }
}
