<?php

declare(strict_types=1);

namespace Tests;

use App\Platform\Identity\MogSsoUser;
use Carbon\CarbonImmutable;
use Database\Fixture\FixtureSchema;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Every test gets a fresh in-memory SQLite mirror of the shared Oracle schema
 * (attached databases for g_store, mog_users, tracking, ...). Never real Oracle.
 */
abstract class TestCase extends BaseTestCase
{
    /** Ported legacy tests that build their own tables opt out of the shared fixture. */
    protected bool $fixtureSchema = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::purge('oracle');
        if ($this->fixtureSchema) {
            FixtureSchema::create();
        }
    }

    protected function db(): Connection
    {
        return DB::connection('oracle');
    }

    /** SSO user with the given SYSTEM_PERMISSIONS keys; returns the authenticated model. */
    protected function actingAsUser(int $id, array $keys = [], int $trackingId = 0): MogSsoUser
    {
        $this->db()->table('mog_users.users_list')->insert(['id' => $id, 'full_name' => "User {$id}", 'tracking_id' => $trackingId ?: $id + 100, 'is_active' => 1]);
        foreach ($keys as $key) {
            $this->db()->table('control_unit.system_permissions')->insert(['user_id' => $id, 'system_key' => $key, 'granted_by' => 'test']);
        }
        $user = MogSsoUser::query()->findOrFail($id);
        $this->actingAs($user, 'admin');

        return $user;
    }

    /** Oracle station link: vehicle ↔ stage ↔ user station with the given ACCESS_TYPE. */
    protected function stationVehicle(int $userId, int $vehicleId, int $accessType, ?string $removedBy = null): void
    {
        $stage = 10_000 + $vehicleId;
        $this->db()->table('g_store.vehicle')->insertOrIgnore(['oid' => $vehicleId, 'plate_num' => "P{$vehicleId}", 'vehicle_num' => 'Truck']);
        $this->db()->table('g_store.vehicle_stages')->insert(['vehicle_oid' => $vehicleId, 'stage_oid' => $stage, 'access_type' => $accessType, 'removed_user' => $removedBy]);
        $this->db()->table('tracking.user_station')->insert(['stage_oid' => $stage, 'user_oid' => $userId + 100]);
    }

    protected function trip(array $attributes = []): int
    {
        $start = CarbonImmutable::now()->subHours(3);

        return $this->db()->table('trips')->insertGetId([
            'oracle_vehicle_id' => '700', 'vehicle_plate' => 'P700', 'driver_name' => 'Driver',
            'status' => 'confirmed', 'review_status' => 'pending', 'entry_source' => 'mobile',
            'started_at' => $start, 'ended_at' => $start->addHour(), 'points_received' => 80, 'points_expected' => 80,
            'created_at' => $start, 'updated_at' => $start,
            ...$attributes,
        ]);
    }
}
