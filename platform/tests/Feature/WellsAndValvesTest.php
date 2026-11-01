<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Valves\Repositories\ValvesRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WellsAndValvesTest extends TestCase
{
    public function test_missing_readings_use_reading_date_and_ignore_soft_deleted_rows(): void
    {
        $this->actingAsUser(5, ['wells']);
        foreach ([1, 2, 3] as $oid) {
            $this->db()->table('wells')->insert(['oid' => $oid, 'name' => "W{$oid}", 'is_active' => $oid < 3 ? 1 : 0]);
        }
        $this->db()->table('inspectors')->insert(['oid' => 1, 'name' => 'I']);
        // Well 1: read today. Well 2: only a soft-deleted reading today plus one synced today for yesterday.
        $this->db()->table('well_readings')->insert([
            ['well_oid' => 1, 'inspector_oid' => 1, 'reading_date' => now(), 'created_at' => now(), 'deleted_at' => null],
            ['well_oid' => 2, 'inspector_oid' => 1, 'reading_date' => now(), 'created_at' => now(), 'deleted_at' => now()],
            ['well_oid' => 2, 'inspector_oid' => 1, 'reading_date' => now()->subDay(), 'created_at' => now(), 'deleted_at' => null],
        ]);

        $this->get('/wells')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wells/Today')
            ->where('stats.active_wells', 2)
            ->where('stats.readings_today', 1)
            ->has('missing', 1)
            ->where('missing.0.name', 'W2'));
    }

    public function test_valve_state_counts_only_each_valves_latest_action(): void
    {
        $this->db()->table('valve_logs')->insert([
            ['valve_oid' => 1, 'action' => 'open', 'action_at' => now()->subHours(3)],
            ['valve_oid' => 1, 'action' => 'close', 'action_at' => now()->subHour()],
            ['valve_oid' => 2, 'action' => 'open', 'action_at' => now()->subHours(2)],
        ]);

        $this->assertSame(['close' => 1, 'open' => 1], collect(app(ValvesRepository::class)->latestActionDistribution())->sortKeys()->all());
    }

    public function test_marking_signals_read(): void
    {
        $this->actingAsUser(5, ['wells']);
        $this->db()->table('wells')->insert(['oid' => 1, 'name' => 'W1']);
        $this->db()->table('well_notifications')->insert([
            ['oid' => 1, 'well_oid' => 1, 'inspector_oid' => 1, 'message' => 'a', 'is_read' => 0],
            ['oid' => 2, 'well_oid' => 1, 'inspector_oid' => 1, 'message' => 'b', 'is_read' => 0],
        ]);

        $this->post('/wells/signals/1/read')->assertRedirect();
        $this->assertSame(1, $this->db()->table('well_notifications')->where('is_read', 0)->count());
        $this->post('/wells/signals/read')->assertRedirect();
        $this->assertSame(0, $this->db()->table('well_notifications')->where('is_read', 0)->count());
    }

    public function test_reading_search_matches_only_the_searched_well(): void
    {
        $this->actingAsUser(5, ['wells']);
        $this->db()->table('wells')->insert([['oid' => 1, 'name' => 'Alpha', 'well_num' => 'W-1'], ['oid' => 2, 'name' => 'Beta', 'well_num' => 'W-2']]);
        $this->db()->table('inspectors')->insert(['oid' => 1, 'name' => 'I']);
        $this->db()->table('well_readings')->insert([
            ['well_oid' => 1, 'inspector_oid' => 1, 'reading_date' => now(), 'created_at' => now()],
            ['well_oid' => 2, 'inspector_oid' => 1, 'reading_date' => now(), 'created_at' => now()],
        ]);

        $this->get('/wells/readings?search=W-2')->assertInertia(fn (Assert $page) => $page
            ->where('readings.total', 1)->where('readings.data.0.well', 'Beta'));
    }
}
