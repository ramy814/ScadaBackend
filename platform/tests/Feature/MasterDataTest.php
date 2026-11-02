<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    public function test_wells_team_and_inspector_crud_with_assignments(): void
    {
        $this->actingAsUser(5, ['wells']);
        $this->db()->table('wells')->insert([['oid' => 1, 'name' => 'W1', 'is_active' => 1], ['oid' => 2, 'name' => 'W2', 'is_active' => 1]]);

        $this->post('/wells/teams', ['NAME' => 'North', 'IS_ACTIVE' => 1, 'well_ids' => [1, 2]])->assertSessionHasNoErrors();
        $team = $this->db()->table('teams')->where('name', 'North')->value('oid');
        $this->assertSame(2, $this->db()->table('team_wells')->where('team_oid', $team)->count());

        $this->post('/wells/inspectors', ['NAME' => 'Ali', 'PHONE' => '0597000000', 'PASSWORD' => 'secret1', 'IS_ACTIVE' => 1, 'team_ids' => [$team]])->assertSessionHasNoErrors();
        $inspector = $this->db()->table('inspectors')->where('phone', '0597000000')->first();
        $this->assertNotSame('secret1', $inspector->password); // hashed by the model cast
        $this->assertSame(1, $this->db()->table('inspector_teams')->where('inspector_oid', $inspector->oid)->count());

        // A team with assignments cannot be deleted (legacy protection).
        $this->delete("/wells/teams/{$team}")->assertSessionHas('error');
        $this->assertSame(1, $this->db()->table('teams')->where('oid', $team)->count());
    }

    public function test_reading_soft_delete_requires_the_action_permission_and_a_reason(): void
    {
        $this->db()->table('wells')->insert(['oid' => 1, 'name' => 'W1']);
        $this->db()->table('inspectors')->insert(['oid' => 1, 'name' => 'I']);
        $reading = $this->db()->table('well_readings')->insertGetId(['well_oid' => 1, 'inspector_oid' => 1, 'reading_date' => now(), 'created_at' => now()]);

        $this->actingAsUser(5, ['wells']);
        $this->delete("/wells/readings/{$reading}", ['reason' => 'duplicate'])->assertForbidden();

        $this->db()->table('control_unit.system_permissions')->insert(['user_id' => 5, 'system_key' => 'wells.readings.delete']);
        $this->delete("/wells/readings/{$reading}", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->delete("/wells/readings/{$reading}", ['reason' => 'Duplicate entry'])->assertSessionHasNoErrors();

        $row = $this->db()->table('well_readings')->where('oid', $reading)->first();
        $this->assertNotNull($row->deleted_at); // row kept for audit
        $this->assertSame('Duplicate entry', $row->delete_reason);
    }

    public function test_valve_team_assignment_and_assign_all(): void
    {
        $this->actingAsUser(5, ['valves']);
        $this->db()->table('eme.water_valves')->insert([['w4_general_id' => 3001], ['w4_general_id' => 3002]]);

        $this->post('/valves/teams', ['NAME' => 'East', 'IS_ACTIVE' => 1, 'ASSIGN_ALL_VALVES' => 0, 'valve_ids' => [3001]])->assertSessionHasNoErrors();
        $team = $this->db()->table('valve_teams')->value('oid');
        $this->assertSame([3001], $this->db()->table('valve_team_valves')->where('team_oid', $team)->pluck('valve_oid')->map(fn ($v) => (int) $v)->all());

        $this->post('/valves/teams', ['NAME' => 'Bad', 'valve_ids' => [9999]])->assertSessionHasErrors('valve_ids.0');
    }

    public function test_tile_proxy_requires_login_and_hides_upstream_failures(): void
    {
        $this->get('/valves/map/tiles/wmts?REQUEST=GetTile')->assertRedirect('/login');

        $this->actingAsUser(5, ['valves']);
        Http::fake(['*' => Http::sequence()->push('denied', 401)->push('PNGDATA', 200, ['Content-Type' => 'image/png'])]);
        $this->get('/valves/map/tiles/wmts?REQUEST=GetTile')->assertOk()->assertHeader('X-WMS-Proxy-Fallback', '1')->assertHeader('Content-Type', 'image/png');
        $this->get('/valves/map/tiles/wms?LAYERS=x')->assertOk()->assertHeaderMissing('X-WMS-Proxy-Fallback');
    }
}
