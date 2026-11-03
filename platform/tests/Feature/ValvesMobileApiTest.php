<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SCADA valves app API: same paths and payloads as the legacy routes/valves.php.
 * Models serialize with UPPERCASE keys (UppercaseKeys trait, identical in the legacy models).
 * Parity note: Valve::logs()/latestLog() use the foreign key `VALVE_OID` while Oracle returns
 * lowercase columns, so eager-loaded LOGS/LATEST_LOG come back empty, exactly as in production.
 */
class ValvesMobileApiTest extends TestCase
{
    private function seedValves(bool $assignAll = false): int
    {
        foreach ([3001, 3002, 3003] as $id) {
            $this->db()->table('eme.water_valves')->insert(['w4_general_id' => $id, 'w4_status' => 'ACTIVE', 'w4_source' => 'الرمال', 'fixture_latitude' => 31.5, 'fixture_longitude' => 34.45]);
        }
        $team = $this->db()->table('valve_teams')->insertGetId(['name' => 'فريق الرمال', 'assign_all_valves' => $assignAll ? 1 : 0]);
        $inspector = $this->db()->table('valve_inspectors')->insertGetId(['name' => 'مفتش', 'phone' => '0599111222', 'password' => Hash::make('secret'), 'is_active' => 1]);
        $this->db()->table('valve_inspector_teams')->insert(['inspector_oid' => $inspector, 'team_oid' => $team]);
        $this->db()->table('valve_team_valves')->insert(['team_oid' => $team, 'valve_oid' => 3001]);

        return $inspector;
    }

    private function login(): array
    {
        return ['Authorization' => 'Bearer '.$this->postJson('/api/valves/auth/login', ['phone' => '0599111222', 'password' => 'secret'])->json('token')];
    }

    public function test_login_returns_the_legacy_payload_and_rejects_bad_credentials(): void
    {
        $inspector = $this->seedValves();

        $this->postJson('/api/valves/auth/login', ['phone' => '0599111222', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonPath('errors.phone.0', 'بيانات الدخول غير صحيحة أو الحساب غير فعال.');
        $this->postJson('/api/valves/auth/login', [])->assertStatus(422)->assertJsonPath('errors.phone.0', 'رقم الجوال مطلوب');

        $this->postJson('/api/valves/auth/login', ['phone' => '0599111222', 'password' => 'secret'])
            ->assertOk()
            ->assertJsonStructure(['token', 'inspector' => ['oid', 'name', 'phone', 'teams' => [['oid', 'name', 'assign_all_valves']]]])
            ->assertJsonPath('inspector.oid', $inspector)
            ->assertJsonPath('inspector.teams.0.assign_all_valves', false);
        $this->assertSame('valves_app', $this->db()->table('personal_access_tokens')->value('name'));
        $this->assertSame('["scada"]', $this->db()->table('personal_access_tokens')->value('abilities'));
    }

    public function test_inspector_sees_and_acts_only_on_assigned_valves(): void
    {
        $this->seedValves();
        $auth = $this->login();

        $this->getJson('/api/valves', $auth)->assertOk()->assertJsonCount(1)->assertJsonPath('0.W4_GENERAL_ID', 3001)->assertJsonPath('0.CODE', 'WV-3001')->assertJsonPath('0.LATITUDE', 31.5);
        $this->getJson('/api/valves/3002', $auth)->assertForbidden()->assertJsonPath('message', 'هذا المحبس غير مسند لفرق المفتش');
        $this->postJson('/api/valves/3002/action', ['action' => 'open'], $auth)->assertForbidden();

        $this->postJson('/api/valves/3001/action', ['action' => 'close', 'local_id' => 'pending-1'], $auth)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', '✅ تم إغلاق محبس WV-3001 بنجاح')
            ->assertJsonPath('valve.CODE', 'WV-3001');
        $this->assertSame('close', $this->db()->table('valve_logs')->value('action'));
        // Offline retry with the same local id: no duplicate (legacy idempotency).
        $this->postJson('/api/valves/3001/action', ['action' => 'close', 'local_id' => 'pending-1'], $auth)->assertOk();
        $this->assertSame(1, $this->db()->table('valve_logs')->count());
        $this->postJson('/api/valves/3001/action', ['action' => 'open', 'local_id' => 'pending-1'], $auth)->assertStatus(409);
        $this->postJson('/api/valves/3001/action', ['action' => 'explode'], $auth)->assertStatus(422);
    }

    public function test_assign_all_team_reaches_every_valve(): void
    {
        $this->seedValves(assignAll: true);

        $this->getJson('/api/valves', $this->login())->assertOk()->assertJsonCount(3);
    }

    public function test_show_and_logs_include_the_acting_inspector(): void
    {
        $inspector = $this->seedValves();
        $auth = $this->login();
        $this->postJson('/api/valves/3001/action', ['action' => 'open'], $auth)->assertOk();

        // Legacy: both endpoints failed (undefined relation `logs.user`); the platform answers.
        $this->getJson('/api/valves/3001', $auth)->assertOk()->assertJsonPath('CODE', 'WV-3001')->assertJsonPath('LOGS', []);
        $this->getJson('/api/valves/3001/logs', $auth)->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.ACTION', 'open')->assertJsonPath('data.0.USER.OID', $inspector)->assertJsonPath('data.0.USER.NAME', 'مفتش');
    }

    public function test_maintenance_requests_are_listed_filtered_and_resolved_by_the_inspector(): void
    {
        $inspector = $this->seedValves();
        $auth = $this->login();
        $this->postJson('/api/valves/3001/action', ['action' => 'replacement', 'note' => 'تسريب'], $auth)->assertOk();

        $report = $this->getJson('/api/valve-maintenance', $auth)->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.PRIORITY', 'high')
            ->assertJsonPath('data.0.DESCRIPTION', 'تسريب')
            ->assertJsonPath('data.0.REPORTER.OID', $inspector)
            ->json('data.0.OID');
        $this->getJson('/api/valve-maintenance?status=resolved', $auth)->assertOk()->assertJsonPath('total', 0);

        $this->putJson("/api/valve-maintenance/{$report}", ['status' => 'resolved'], $auth)->assertOk()->assertJsonPath('STATUS', 'resolved')->assertJsonPath('REPORTER.NAME', 'مفتش');
        $this->assertSame($inspector, (int) $this->db()->table('valve_maintenance_reports')->value('resolved_by'));
        $this->putJson("/api/valve-maintenance/{$report}", ['priority' => 'urgent'], $auth)->assertStatus(422);
    }

    public function test_dashboard_stats_count_the_latest_action_per_valve(): void
    {
        $this->seedValves(assignAll: true);
        $auth = $this->login();
        $this->postJson('/api/valves/3001/action', ['action' => 'open'], $auth);
        $this->db()->table('valve_logs')->where('valve_oid', 3001)->update(['action_at' => now()->subHour()]);
        $this->postJson('/api/valves/3001/action', ['action' => 'close'], $auth);
        $this->postJson('/api/valves/3002/action', ['action' => 'maintenance'], $auth);

        $this->getJson('/api/dashboard/stats', $auth)->assertOk()->assertExactJson([
            'valves' => ['total' => 3, 'open' => 0, 'closed' => 1, 'maintenance' => 1, 'replacement' => 0],
            'wells' => ['total' => 0, 'on' => 0, 'off' => 0, 'maintenance' => 0],
        ]);
        $this->getJson('/api/dashboard/recent-activity', $auth)->assertOk()->assertJsonCount(3)
            ->assertJsonStructure([['type', 'oid', 'asset_code', 'valve_name', 'action', 'note', 'action_at']]);
    }
}
