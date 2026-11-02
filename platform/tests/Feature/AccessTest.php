<?php

declare(strict_types=1);

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessTest extends TestCase
{
    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/fleet/reviews')->assertRedirect('/login');
    }

    public function test_user_without_any_application_is_refused(): void
    {
        $this->actingAsUser(7);

        $this->get('/')->assertForbidden()->assertInertia(fn (Assert $page) => $page->component('Errors/NoAccess')->where('reason', 'no_application'));
    }

    public function test_workspace_requires_its_system_key_and_shell_lists_only_granted_apps(): void
    {
        $this->actingAsUser(7, ['wells']);

        $this->get('/fleet/reviews')->assertForbidden();
        $this->get('/valves')->assertForbidden();
        $this->get('/wells')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Wells/Today')
            ->where('shell.workspace', 'wells')
            ->has('shell.applications', 1)
            ->where('shell.applications.0.key', 'wells')
            ->where('shell.urls.tripLookup', null));
    }

    public function test_access_management_is_limited_to_configured_platform_admins(): void
    {
        $this->actingAsUser(7, ['fleet']);
        $this->get('/platform/access')->assertForbidden();
        $this->get('/platform/operations')->assertForbidden();
        $this->get('/platform/applications')->assertOk();
    }

    public function test_admin_replaces_user_access_and_actions_require_their_system(): void
    {
        $this->actingAsUser(1, ['fleet']);
        $this->db()->table('mog_users.users_list')->insert(['id' => 9, 'full_name' => 'Target', 'is_active' => 1]);

        $this->put('/platform/access/9', ['keys' => ['wells.readings.delete']])->assertSessionHasErrors('keys');

        $this->put('/platform/access/9', ['keys' => ['wells', 'wells.readings.delete']])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(
            ['wells', 'wells.readings.delete'],
            $this->db()->table('control_unit.system_permissions')->where('user_id', 9)->pluck('system_key')->all(),
        );

        $this->put('/platform/access/9', ['keys' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->db()->table('control_unit.system_permissions')->where('user_id', 9)->count());
    }

    public function test_directory_lists_granted_users_and_station_only_assistants(): void
    {
        $this->actingAsUser(1, ['fleet']);
        $this->db()->table('mog_users.users_list')->insert(['id' => 9, 'full_name' => 'Station assistant', 'tracking_id' => 109, 'is_active' => 1]);
        $this->stationVehicle(9, 700, 1);
        $this->stationVehicle(9, 701, 2, 'removed');

        $this->get('/platform/access')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Access')
            ->where('oracleAvailable', true)
            ->has('users', 2)
            ->where('users.0.user_id', 1)
            ->where('users.0.systems', ['fleet'])
            ->where('users.1.user_id', 9)
            ->where('users.1.name', 'Station assistant')
            ->where('users.1.systems', [])
            ->where('users.1.fleet_access', ['can_enter' => true, 'can_review' => false]));
    }

    public function test_fleet_users_without_station_scope_see_locked_manual_screens_with_reason(): void
    {
        $this->actingAsUser(7, ['fleet']);

        $this->get('/fleet/reviews')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shell.oracleAvailable', true)
            ->where('shell.navigation', fn ($groups) => collect($groups)->flatMap(fn ($g) => $g['items'])
                ->whereIn('key', ['manual', 'manual-requests'])->every(fn ($item) => $item['locked'] === true)));

        $this->get('/fleet/manual')->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NoAccess')->where('reason', 'manual_scope'));
    }

    public function test_manual_screens_explain_missing_oracle_connection(): void
    {
        $this->actingAsUser(7, ['fleet']);
        config(['database.connections.oracle.driver' => 'oracle', 'database.connections.oracle.host' => null, 'database.connections.oracle.tns' => null, 'database.connections.oracle.service_name' => null]);

        $this->get('/fleet/manual')->assertForbidden()->assertInertia(fn (Assert $page) => $page
            ->component('Errors/NoAccess')->where('reason', 'oracle_unavailable')->where('shell.oracleAvailable', false));
    }
}
