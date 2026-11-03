<?php

declare(strict_types=1);

namespace Database\Fixture;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * SQLite mirror of the shared Oracle tables the platform reads (lowercase columns,
 * as Oracle PDO returns them). For tests and the local demo only: refuses to run
 * against anything but the SQLite fixture connection. Production never migrates.
 */
final class FixtureSchema
{
    public static function create(): void
    {
        if (config('database.connections.oracle.driver') !== 'sqlite') {
            throw new RuntimeException('FixtureSchema only runs on the SQLite fixture connection.');
        }

        $s = Schema::connection('oracle');
        $timestamps = fn (Blueprint $t) => $t->timestamps();

        // ── External Oracle schemas ─────────────────────────────────────────
        $s->create('mog_users.users_list', function (Blueprint $t): void {
            $t->integer('id')->primary();
            $t->integer('emp_id')->nullable();
            $t->string('full_name')->nullable();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('user_mobile')->nullable();
            $t->integer('tracking_id')->nullable();
            $t->integer('is_active')->default(1);
        });
        $s->create('control_unit.system_permissions', function (Blueprint $t): void {
            $t->increments('id');
            $t->integer('user_id');
            $t->string('system_key');
            $t->string('granted_by')->nullable();
            $t->timestamp('granted_at')->useCurrent();
        });
        $s->create('g_store.beneficiaries', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->string('full_name')->nullable();
            $t->string('mobile_num')->nullable();
            $t->string('national_id')->nullable();
            $t->integer('is_active')->default(1);
        });
        $s->create('g_store.vehicle', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->string('plate_num')->nullable();
            $t->string('vehicle_num')->nullable();
            $t->integer('vehicle_type_oid')->nullable();
            $t->integer('odometer')->nullable();
            $t->integer('beneficiary_oid')->nullable();
            $t->integer('is_active')->default(1);
            $t->decimal('hourly_price', 10, 2)->nullable();
        });
        $s->create('g_store.vehicle_stages', function (Blueprint $t): void {
            $t->integer('vehicle_oid');
            $t->integer('stage_oid');
            $t->integer('access_type')->nullable();
            $t->string('removed_user')->nullable();
        });
        $s->create('tracking.user_station', function (Blueprint $t): void {
            $t->integer('stage_oid');
            $t->integer('user_oid');
        });
        $s->create('eme.water_valves', function (Blueprint $t): void {
            $t->integer('w4_general_id')->primary();
            $t->integer('w4_street_no')->nullable();
            foreach (['w4_valve_type', 'w4_manufacturer', 'w4_operation', 'w4_status', 'w4_source', 'w4_valve_open'] as $c) {
                $t->string($c)->nullable();
            }
            $t->float('w4_valve_diameter')->nullable();
            $t->integer('w4_project_no')->nullable();
            $t->float('w4_quality')->nullable();
            $t->integer('w4_valve_no')->nullable();
            // Fixture-only stand-in for SDO_GEOMETRY (see ValveRepository::attachWgs84Coordinates()).
            $t->double('fixture_latitude')->nullable();
            $t->double('fixture_longitude')->nullable();
        });

        // ── Fleet (managed by the legacy backend) ───────────────────────────
        $s->create('trips', function (Blueprint $t): void {
            $t->id();
            $t->string('local_id')->nullable();
            foreach (['oracle_driver_id', 'driver_name', 'driver_phone', 'driver_employee_number', 'oracle_vehicle_id', 'vehicle_plate', 'vehicle_name', 'vehicle_type',
                'origin_type', 'origin_id', 'origin_text', 'destination_type', 'destination_id', 'destination_text', 'mission_type', 'driver_id', 'vehicle_id', 'client_checksum', 'server_checksum', 'entry_source', 'entered_by_name', 'reviewed_by'] as $c) {
                $t->string($c)->nullable();
            }
            $t->string('status')->default('uploaded');
            $t->string('review_status')->default('pending');
            $t->text('notes')->nullable();
            $t->text('review_note')->nullable();
            $t->text('clarification_response')->nullable();
            foreach (['started_at', 'ended_at', 'confirmed_at', 'reviewed_at', 'clarification_responded_at'] as $c) {
                $t->timestamp($c)->nullable();
            }
            foreach (['start_odometer', 'end_odometer', 'points_expected', 'points_received', 'wait_minutes', 'duration_minutes', 'quality_version', 'entered_by_user_id'] as $c) {
                $t->integer($c)->nullable();
            }
            $t->decimal('virtual_fuel_liters', 12, 3)->nullable();
            $t->decimal('distance_km', 12, 3)->nullable();
            $t->decimal('hourly_price', 10, 2)->nullable();
            $t->timestamps();
        });
        $s->create('trip_points', function (Blueprint $t): void {
            $t->id();
            $t->integer('trip_id');
            $t->integer('sequence_number');
            $t->double('latitude');
            $t->double('longitude');
            foreach (['accuracy', 'speed', 'heading', 'battery_level'] as $c) {
                $t->double($c)->nullable();
            }
            $t->string('provider')->nullable();
            $t->timestamp('captured_at')->nullable();
            $t->timestamp('recorded_at')->nullable();
            $t->boolean('client_is_valid')->default(true);
            $t->string('quality_reason')->nullable();
            $t->timestamps();
        });
        $s->create('trip_stops', function (Blueprint $t): void {
            $t->id();
            $t->integer('trip_id');
            $t->string('local_id')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('ended_at')->nullable();
            $t->double('latitude')->nullable();
            $t->double('longitude')->nullable();
            $t->string('source')->nullable();
            $t->string('reason')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        $s->create('trip_tracking_events', function (Blueprint $t): void {
            $t->id();
            $t->integer('trip_id');
            $t->string('local_id')->nullable();
            $t->string('event_type');
            $t->timestamp('detected_at')->nullable();
            $t->double('latitude')->nullable();
            $t->double('longitude')->nullable();
            $t->text('details_json')->nullable();
            $t->timestamps();
        });
        $s->create('trip_passengers', function (Blueprint $t): void {
            $t->id();
            $t->integer('trip_id');
            $t->string('name')->nullable();
            $t->string('employee_id')->nullable();
            $t->string('source')->nullable();
            $t->timestamps();
        });
        $s->create('audit_logs', function (Blueprint $t): void {
            $t->id();
            $t->string('event_type');
            $t->string('entity_type');
            $t->integer('entity_id');
            $t->string('actor')->nullable();
            $t->text('payload')->nullable();
            $t->timestamps();
        });
        $s->create('driver_app_installations', function (Blueprint $t): void {
            $t->id();
            foreach (['oracle_driver_id', 'device_id', 'driver_name', 'driver_phone', 'driver_employee_number', 'app_version', 'package_name', 'installer_store'] as $c) {
                $t->string($c)->nullable();
            }
            $t->integer('build_number')->nullable();
            foreach (['installed_at', 'app_updated_at', 'first_seen_at', 'last_seen_at'] as $c) {
                $t->timestamp($c)->nullable();
            }
            $t->timestamps();
        });
        foreach (['mission_locations', 'mission_types'] as $table) {
            $s->create($table, function (Blueprint $t) use ($table): void {
                $t->id();
                $t->string('name');
                if ($table === 'mission_locations') {
                    $t->string('category')->default('both');
                }
                $t->integer('sort_order')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        // Same columns as the legacy personal_access_tokens migration (TOKENABLE_ID is a string there).
        $s->create('personal_access_tokens', function (Blueprint $t): void {
            $t->id();
            $t->string('tokenable_type');
            $t->string('tokenable_id');
            $t->index(['tokenable_type', 'tokenable_id']);
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        $s->create('system_operational_states', function (Blueprint $t): void {
            $t->id();
            $t->string('scope')->unique();
            $t->boolean('is_suspended')->default(false);
            $t->text('reason')->nullable();
            $t->integer('changed_by_user_id')->nullable();
            $t->string('changed_by_name')->nullable();
            $t->timestamp('changed_at')->nullable();
        });

        // ── Wells ───────────────────────────────────────────────────────────
        $s->create('wells', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            foreach (['well_num', 'name', 'damage_status', 'operational_status', 'pump_type', 'power_source', 'tank_shape', 'tank_material',
                'chlorination_system', 'chlorination_status', 'chlorine_tank_type', 'chlorine_tank_material', 'chlorine_pump_type',
                'cooling_tank_type', 'cooling_system', 'cooling_line_type'] as $c) {
                $t->string($c)->nullable();
            }
            foreach (['well_depth', 'pump_power_hp', 'capacity', 'salinity', 'tank_diameter', 'tank_height', 'chlorine_tank_capacity', 'cooling_tank_capacity'] as $c) {
                $t->decimal($c, 12, 2)->nullable();
            }
            $t->integer('quarter_oid')->nullable();
            $t->date('installation_date')->nullable();
            $t->date('last_maintenance_date')->nullable();
            $t->decimal('latitude', 10, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->integer('is_active')->default(1);
            $timestamps($t);
        });
        $s->create('well_generators', function (Blueprint $t): void {
            $t->increments('oid');
            $t->integer('well_oid');
            $t->integer('generator_oid');
            $t->decimal('fuel_consumption_lph', 10, 2)->nullable();
            $t->decimal('consumption_tolerance_lph', 10, 2)->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $s->create('well_media', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('well_oid');
            $t->string('category');
            $t->string('label')->nullable();
            $t->string('file_path');
            $t->string('original_name')->nullable();
            $t->string('mime_type')->nullable();
            $timestamps($t);
        });
        $s->create('diesel_inventories', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('well_oid');
            $t->integer('inspector_oid');
            $t->integer('generator_oid')->nullable();
            $t->timestamp('inventory_date')->nullable();
            foreach (['generator_hours_reading', 'diesel_height_cm', 'available_fuel_liters', 'latitude', 'longitude'] as $c) {
                $t->decimal($c, 14, 4)->nullable();
            }
            $t->text('notes')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->string('client_local_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->integer('deleted_by_user_id')->nullable();
            $t->string('deleted_by_name')->nullable();
            $t->string('delete_reason')->nullable();
            $timestamps($t);
        });
        $s->create('reading_generator_logs', function (Blueprint $t): void {
            $t->increments('oid');
            $t->integer('reading_oid');
            $t->integer('generator_oid');
            $t->decimal('current_reading', 12, 2)->nullable();
            $t->decimal('gallons', 12, 2)->nullable();
            $t->timestamp('created_at')->nullable();
        });
        $s->create('dss_core.wtr_wells', function (Blueprint $t): void {
            $t->string('well_id');
            $t->string('source_well_code')->nullable();
        });
        $s->create('gazadata.quarters', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->string('name');
        });
        $s->create('g_store.generator', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->string('name')->nullable();
            $t->string('generator_num')->nullable();
            $t->integer('is_active')->default(1);
            $t->integer('type_oid')->default(128);
            $t->decimal('engine_capacity', 10, 2)->nullable();
            $t->decimal('fuel_consumption', 10, 2)->nullable();
        });
        $s->create('g_store.fuel_log', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->integer('status_oid')->nullable();
            $t->timestamp('fill_up_date')->nullable();
            $t->timestamp('entry_date')->nullable();
            $t->timestamp('cancel_date')->nullable();
        });
        $s->create('g_store.fuel_log_details', function (Blueprint $t): void {
            $t->integer('oid')->primary();
            $t->integer('fuel_log_oid');
            $t->integer('asset_oid');
            $t->integer('asset_type');
            $t->string('current_reading')->nullable();
            $t->decimal('gallons', 12, 2)->nullable();
            $t->decimal('approved_gallons', 12, 2)->nullable();
        });
        $s->create('teams', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('name');
            $t->integer('is_active')->default(1);
            $timestamps($t);
        });
        $s->create('inspectors', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('password')->nullable();
            $t->integer('is_active')->default(1);
            $timestamps($t);
        });
        foreach (['team_wells' => ['team_oid', 'well_oid'], 'inspector_teams' => ['inspector_oid', 'team_oid']] as $table => [$a, $b]) {
            $s->create($table, function (Blueprint $t) use ($a, $b): void {
                $t->increments('oid');
                $t->integer($a);
                $t->integer($b);
                $t->timestamp('created_at')->nullable();
            });
        }
        $s->create('well_readings', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('well_oid');
            $t->integer('inspector_oid');
            foreach (['flow_rate', 'generator_hours_reading', 'pump_hours_reading', 'chlorine', 'salinity'] as $c) {
                $t->decimal($c, 12, 2)->nullable();
            }
            $t->string('quality')->nullable();
            $t->timestamp('reading_date')->nullable();
            $t->timestamp('synced_at')->nullable();
            $t->string('client_local_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->integer('deleted_by_user_id')->nullable();
            $t->string('deleted_by_name')->nullable();
            $t->string('delete_reason')->nullable();
            $timestamps($t);
        });
        $s->create('notification_types', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('name');
            $t->string('icon')->nullable();
            $t->string('emoji')->nullable();
            $t->string('color')->nullable();
            $t->integer('priority')->default(5);
            $t->integer('is_active')->default(1);
            $timestamps($t);
        });
        $s->create('well_notifications', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('well_oid');
            $t->integer('notification_type_oid')->nullable();
            $t->integer('inspector_oid');
            $t->text('message')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->string('client_local_id')->nullable();
            $timestamps($t);
        });
        $s->create('well_activity_media', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('activity_type');
            $t->integer('activity_oid');
            $t->string('client_local_id')->nullable();
            $t->string('file_path');
            $t->string('original_name')->nullable();
            $t->string('mime_type')->nullable();
            $t->integer('file_size')->nullable();
            $timestamps($t);
        });

        // ── Valves ──────────────────────────────────────────────────────────
        $s->create('valve_logs', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('valve_oid');
            $t->integer('user_oid')->nullable();
            $t->string('action');
            $t->text('note')->nullable();
            $t->text('sms_raw')->nullable();
            $t->timestamp('action_at')->nullable();
            $t->string('client_local_id')->nullable();
            $timestamps($t);
        });
        $s->create('valve_maintenance_reports', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->integer('valve_oid');
            $t->integer('reported_by')->nullable();
            $t->text('description')->nullable();
            $t->string('priority')->nullable();
            $t->string('status')->default('pending');
            $t->integer('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $timestamps($t);
        });
        $s->create('valve_teams', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('name');
            $t->integer('is_active')->default(1);
            $t->integer('assign_all_valves')->default(0);
            $timestamps($t);
        });
        $s->create('valve_inspectors', function (Blueprint $t) use ($timestamps): void {
            $t->increments('oid');
            $t->string('name');
            $t->string('phone')->nullable();
            $t->string('password')->nullable();
            $t->integer('is_active')->default(1);
            $timestamps($t);
        });
        foreach (['valve_team_valves' => ['team_oid', 'valve_oid'], 'valve_inspector_teams' => ['inspector_oid', 'team_oid']] as $table => [$a, $b]) {
            $s->create($table, function (Blueprint $t) use ($a, $b): void {
                $t->increments('oid');
                $t->integer($a);
                $t->integer($b);
                $t->timestamp('created_at')->nullable();
            });
        }
    }
}
