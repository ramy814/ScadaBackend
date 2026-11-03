<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Fleet\Services\TripSyncService;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Driver app journey over HTTP: SMS login, vehicles, offline trip sync, reconcile, clarification. */
class DriverMobileApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db()->table('g_store.beneficiaries')->insert(['oid' => 501, 'full_name' => 'أحمد سالم', 'mobile_num' => '0599123401', 'national_id' => '400100200', 'is_active' => 1]);
        $this->db()->table('g_store.vehicle')->insert(['oid' => 701, 'plate_num' => '3-1234', 'vehicle_num' => 'شاحنة', 'beneficiary_oid' => 501, 'is_active' => 1]);
    }

    private function login(): array
    {
        $this->postJson('/api/driver/auth/send-code', ['phone' => '0599123401'])->assertOk();
        $token = $this->postJson('/api/driver/auth/verify', ['phone' => '0599123401', 'code' => '424242', 'device_name' => 'test-device'])->assertOk()->json('token');

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_fixed_code_is_rejected_unless_explicitly_configured(): void
    {
        config(['services.sms.test_code' => null]);
        $this->postJson('/api/driver/auth/send-code', ['phone' => '0599123401'])->assertOk()->assertJsonPath('message', 'تم ارسال رمز التحقق.');
        // Legacy accepted '000000' whenever SMS_TEST_CODE was empty.
        $this->postJson('/api/driver/auth/verify', ['phone' => '0599123401', 'code' => '000000'])->assertStatus(422);

        config(['services.sms.test_code' => '424242']);
        $this->postJson('/api/driver/auth/verify', ['phone' => '0599123401', 'code' => '424242'])->assertOk()->assertJsonStructure(['token', 'user']);
        $this->assertSame(['App\\Models\\Beneficiary', '["driver"]'], array_values((array) $this->db()->table('personal_access_tokens')->first(['tokenable_type', 'abilities'])));
    }

    public function test_unknown_phone_is_refused_and_debug_header_reveals_nothing(): void
    {
        $this->postJson('/api/driver/auth/send-code', ['phone' => '0599000000'], ['X-Driver-Debug' => 'lookup'])
            ->assertForbidden()->assertJsonMissingPath('debug');
    }

    public function test_offline_trip_is_stored_uploaded_confirmed_and_reconciled(): void
    {
        config(['services.sms.test_code' => '424242']);
        $auth = $this->login();

        $this->getJson('/api/driver/me', $auth)->assertOk()->assertJsonPath('driver.name', 'أحمد سالم');
        $this->getJson('/api/driver/my-vehicles', $auth)->assertOk()->assertJsonPath('vehicles.0.plate_number', '3-1234');
        $this->getJson('/api/driver/locations', $auth)->assertOk()->assertJsonStructure(['origins', 'destinations']);
        $this->getJson('/api/driver/mission-types', $auth)->assertOk()->assertJsonStructure(['mission_types']);

        $start = now()->subHour()->startOfMinute();
        $localId = (string) Str::uuid();
        $tripPayload = [
            'local_id' => $localId, 'vehicle_plate' => '3-1234', 'mission_type' => 'نقل معدات',
            'origin' => ['type' => 'free_text', 'text' => 'مقر البلدية'], 'destination' => ['type' => 'free_text', 'text' => 'مخازن البلدية'],
            'started_at' => $start->toIso8601String(), 'ended_at' => $start->copy()->addMinutes(20)->toIso8601String(),
        ];
        $store = $this->postJson('/api/driver/trips', $tripPayload, $auth);
        $store->assertCreated()->assertJsonPath('local_id', $localId);
        $tripId = $store->json('server_id');
        // Offline retry of the same trip does not create a duplicate.
        $this->postJson('/api/driver/trips', $tripPayload, $auth)->assertCreated()->assertJsonPath('server_id', $tripId);

        $points = collect(range(0, 9))->map(fn (int $i): array => [
            'sequence_number' => $i + 1, 'latitude' => 31.50 + $i * 0.001, 'longitude' => 34.46, 'accuracy' => 5, 'speed' => 8,
            'captured_at' => $start->copy()->addMinutes($i * 2)->toIso8601String(),
        ])->all();
        $this->postJson("/api/driver/trips/{$tripId}/points", ['points' => $points], $auth)->assertOk()->assertJsonPath('points_received', 10);

        // Wrong checksum: the server keeps the trip as uploaded and the phone must keep its copy.
        $this->postJson("/api/driver/trips/{$tripId}/confirm", ['points_expected' => 10, 'checksum' => str_repeat('a', 64)], $auth)
            ->assertOk()->assertJsonPath('status', 'uploaded')->assertJsonPath('can_delete_local', false);
        $checksum = app(TripSyncService::class)->checksumForPoints($points);
        $this->postJson("/api/driver/trips/{$tripId}/confirm", ['points_expected' => 10, 'checksum' => $checksum], $auth)
            ->assertOk()->assertJsonPath('status', 'confirmed')->assertJsonPath('can_delete_local', true);
        $this->getJson("/api/driver/trips/{$tripId}/route-preview", $auth)->assertOk();

        $reconcile = $this->getJson("/api/driver/trips/reconcile?local_ids={$localId},never-uploaded", $auth)->assertOk();
        $reconcile->assertJsonPath('count', 2)->assertJsonPath('items.0.uploaded', true)->assertJsonPath('items.1.status', 'missing');

        $this->getJson('/api/driver/trips?per_page=10', $auth)->assertOk()->assertJsonPath('trips.0.local_id', $localId)->assertJsonPath('pagination.total', 1);
    }

    public function test_driver_answers_a_clarification_once_and_only_for_own_trip(): void
    {
        config(['services.sms.test_code' => '424242']);
        $auth = $this->login();
        $mine = $this->trip(['oracle_driver_id' => '501', 'review_status' => 'needs_clarification', 'review_note' => 'لماذا تغير المسار؟']);
        $other = $this->trip(['oracle_driver_id' => '999', 'review_status' => 'needs_clarification']);

        $this->getJson('/api/driver/clarifications', $auth)->assertOk()->assertJsonCount(1, 'clarifications')->assertJsonPath('clarifications.0.trip_id', $mine);
        $this->postJson("/api/driver/trips/{$other}/clarification-response", ['response' => 'x'], $auth)->assertForbidden();
        $this->postJson("/api/driver/trips/{$mine}/clarification-response", ['response' => 'طريق مغلق'], $auth)->assertOk()->assertJsonPath('status', 'responded');
        $this->assertSame('طريق مغلق', $this->db()->table('trips')->where('id', $mine)->value('clarification_response'));
        $this->assertSame('driver:501', $this->db()->table('audit_logs')->where('event_type', 'trip.clarification.responded')->value('actor'));
    }

    public function test_presence_and_logout(): void
    {
        config(['services.sms.test_code' => '424242']);
        $auth = $this->login();

        $this->postJson('/api/driver/app-presence', ['device_id' => (string) Str::uuid(), 'app_version' => '1.1.19', 'build_number' => 42, 'package_name' => 'org.mogaza.driver', 'installer_store' => 'play'], $auth)
            ->assertOk()->assertJsonStructure(['data' => ['last_seen_at', 'latest_version', 'latest_build', 'update_required']]);
        $this->postJson('/api/driver/auth/logout', [], $auth)->assertOk();
        $this->assertSame(0, $this->db()->table('personal_access_tokens')->count());
    }
}
