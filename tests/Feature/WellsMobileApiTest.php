<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Wells inspector app API: same paths and payloads as the legacy routes/api.php `wells` group. */
class WellsMobileApiTest extends TestCase
{
    private int $inspector;

    private int $well;

    protected function setUp(): void
    {
        parent::setUp();
        $this->well = $this->db()->table('wells')->insertGetId(['name' => 'بئر الصفطاوي', 'is_active' => 1, 'tank_shape' => 'Daily Tank']);
        $other = $this->db()->table('wells')->insertGetId(['name' => 'بئر آخر', 'is_active' => 1]);
        $team = $this->db()->table('teams')->insertGetId(['name' => 'فريق الشمال', 'is_active' => 1]);
        $this->inspector = $this->db()->table('inspectors')->insertGetId(['name' => 'مفتش', 'phone' => '0599222333', 'password' => Hash::make('secret'), 'is_active' => 1]);
        $this->db()->table('inspector_teams')->insert(['inspector_oid' => $this->inspector, 'team_oid' => $team]);
        $this->db()->table('team_wells')->insert(['team_oid' => $team, 'well_oid' => $this->well]);
        $this->db()->table('notification_types')->insert([['name' => 'تسريب', 'is_active' => 1], ['name' => 'قديم', 'is_active' => 0]]);
        unset($other);
    }

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->postJson('/api/wells/auth/login', ['phone' => '0599222333', 'password' => 'secret'])->json('token')];
    }

    public function test_login_payload_token_and_legacy_english_framework_messages(): void
    {
        $this->postJson('/api/wells/auth/login', ['phone' => '0599222333', 'password' => 'secret'])
            ->assertOk()
            ->assertJsonStructure(['token', 'inspector' => ['oid', 'name', 'phone', 'team_oid']])
            ->assertJsonPath('inspector.oid', $this->inspector);
        $this->assertSame(['App\\Models\\Wells\\Inspector', 'wells_app', '["wells"]'], array_values((array) $this->db()->table('personal_access_tokens')->first(['tokenable_type', 'name', 'abilities'])));

        $this->postJson('/api/wells/auth/login', ['phone' => '0599222333', 'password' => 'bad'])->assertStatus(422)->assertJsonPath('errors.phone.0', 'بيانات الدخول غير صحيحة.');
        // Legacy has no translations and runs APP_LOCALE=en.
        $this->postJson('/api/wells/auth/login', [])->assertStatus(422)->assertJsonPath('errors.phone.0', 'The phone field is required.');
    }

    public function test_sync_lists_only_team_wells_and_active_notification_types(): void
    {
        $auth = $this->auth();

        $wells = $this->getJson('/api/wells/sync/wells', $auth)->assertOk()->json('wells');
        $this->assertCount(1, $wells);
        $this->getJson('/api/wells/sync/notification-types', $auth)->assertOk()->assertJsonCount(1, 'notification_types')->assertJsonPath('notification_types.0.name', 'تسريب');
    }

    public function test_offline_readings_sync_is_idempotent_by_local_id(): void
    {
        $auth = $this->auth();
        $payload = ['readings' => [['LOCAL_ID' => 'r-1', 'WELL_OID' => $this->well, 'READING_DATE' => '2026-11-03 08:00:00', 'GENERATOR_HOURS_READING' => 1520.5]]];

        $first = $this->postJson('/api/wells/readings/sync', $payload, $auth)->assertOk()->assertJsonPath('synced_ids.0', 'r-1')->json();
        $this->postJson('/api/wells/readings/sync', $payload, $auth)->assertOk()->assertJsonPath('synced_ids.0', 'r-1');
        $this->assertSame(1, $this->db()->table('well_readings')->count());
        $this->assertArrayHasKey('synced_records', $first);

        $this->postJson('/api/wells/readings/sync', ['readings' => [['WELL_OID' => $this->well]]], $auth)->assertStatus(422);
    }

    public function test_diesel_and_notification_sync_and_history(): void
    {
        $auth = $this->auth();
        $this->postJson('/api/wells/diesel-inventories/sync', ['inventories' => [['LOCAL_ID' => 'd-1', 'WELL_OID' => $this->well, 'INVENTORY_DATE' => '2026-11-03 09:00:00', 'GENERATOR_HOURS_READING' => 1600, 'DIESEL_HEIGHT_CM' => 80]]], $auth)
            ->assertOk()->assertJsonPath('synced_ids.0', 'd-1');
        $type = $this->db()->table('notification_types')->where('is_active', 1)->value('oid');
        $this->postJson('/api/wells/notifications/sync', ['notifications' => [['LOCAL_ID' => 'n-1', 'WELL_OID' => $this->well, 'NOTIFICATION_TYPE_OID' => $type, 'MESSAGE' => 'تسريب في الخط']]], $auth)
            ->assertOk()->assertJsonPath('synced_ids.0', 'n-1');

        $this->getJson('/api/wells/sync/history', $auth)->assertOk()
            ->assertJsonCount(0, 'readings')->assertJsonCount(1, 'diesel_inventories')->assertJsonCount(1, 'notifications');
    }

    public function test_activity_photos_upload_only_for_the_inspectors_own_records(): void
    {
        Storage::fake('legacy_media');
        $auth = $this->auth();
        $this->postJson('/api/wells/readings/sync', ['readings' => [['LOCAL_ID' => 'r-9', 'WELL_OID' => $this->well, 'READING_DATE' => '2026-11-03 08:00:00', 'FLOW_RATE' => 12]]], $auth)->assertOk();
        $reading = $this->db()->table('well_readings')->value('oid');
        $id = (string) Str::uuid();

        $this->post('/api/wells/activity-media/sync', ['activity_type' => 'reading', 'activity_oid' => $reading, 'photos' => [UploadedFile::fake()->image('a.jpg')], 'photo_local_ids' => [$id]], $auth + ['Accept' => 'application/json'])
            ->assertOk()->assertExactJson(['uploaded_ids' => [$id]]);
        $this->post('/api/wells/activity-media/sync', ['activity_type' => 'reading', 'activity_oid' => 999, 'photos' => [UploadedFile::fake()->image('b.jpg')], 'photo_local_ids' => [(string) Str::uuid()]], $auth + ['Accept' => 'application/json'])
            ->assertNotFound();
    }

    public function test_wells_routes_require_the_wells_ability_and_principal(): void
    {
        $this->getJson('/api/wells/sync/wells')->assertUnauthorized();
        $valveInspector = $this->db()->table('valve_inspectors')->insertGetId(['name' => 'V', 'phone' => '1', 'password' => Hash::make('s'), 'is_active' => 1]);
        $this->db()->table('personal_access_tokens')->insert(['tokenable_type' => 'App\\Models\\Valves\\ValveInspector', 'tokenable_id' => (string) $valveInspector, 'name' => 'valves_app', 'token' => hash('sha256', 'v'), 'abilities' => '["scada"]']);
        $id = $this->db()->table('personal_access_tokens')->value('id');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/wells/sync/wells', ['Authorization' => "Bearer {$id}|v"])->assertForbidden();
    }
}
