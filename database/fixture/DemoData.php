<?php

declare(strict_types=1);

namespace Database\Fixture;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Local demonstration data for the SQLite fixture (screens, screenshots, manual QA).
 * It is never shown as production data: the command refuses to run outside
 * APP_ENV=local, and production reads the shared Oracle schema only.
 */
final class DemoData
{
    public const ADMIN_ID = 1;

    /** Demo identities offered by /dev-login on the fixture only (id => scope label key). */
    /** G_STORE.VEHICLE.HOURLY_PRICE for demo vehicles 700..705 (null = no price). */
    private const HOURLY_PRICES = [45.0, 60.0, 35.5, 120.0, 50.0, null];

    public const IDENTITIES = [1 => 'admin', 5 => 'fleet', 2 => 'wells', 3 => 'valves', 4 => 'assistant'];

    public static function seed(): void
    {
        if (config('database.connections.oracle.driver') !== 'sqlite') {
            throw new RuntimeException('DemoData only runs on the SQLite fixture connection.');
        }

        mt_srand(7);
        $db = DB::connection('oracle');
        $now = CarbonImmutable::now()->startOfMinute();

        $db->table('mog_users.users_list')->insert([
            ['id' => self::ADMIN_ID, 'emp_id' => 9001, 'full_name' => 'مستخدم العرض المحلي', 'tracking_id' => 100, 'is_active' => 1, 'user_mobile' => '0599000001'],
            ['id' => 2, 'emp_id' => 9002, 'full_name' => 'مشرف آبار تجريبي', 'tracking_id' => 101, 'is_active' => 1, 'user_mobile' => '0599000002'],
            ['id' => 3, 'emp_id' => 9003, 'full_name' => 'مشرف محابس تجريبي', 'tracking_id' => 102, 'is_active' => 1, 'user_mobile' => '0599000003'],
            ['id' => 5, 'emp_id' => 9005, 'full_name' => 'مشرف أسطول تجريبي', 'tracking_id' => 104, 'is_active' => 1, 'user_mobile' => '0599000005'],
        ]);
        foreach (['fleet', 'wells', 'valves', 'fleet.vehicle-claims.view'] as $key) {
            $db->table('control_unit.system_permissions')->insert(['user_id' => self::ADMIN_ID, 'system_key' => $key, 'granted_by' => 'demo']);
        }
        $db->table('control_unit.system_permissions')->insert(['user_id' => 2, 'system_key' => 'wells', 'granted_by' => 'demo']);
        $db->table('control_unit.system_permissions')->insert(['user_id' => 2, 'system_key' => 'wells.readings.delete', 'granted_by' => 'demo']);
        $db->table('control_unit.system_permissions')->insert(['user_id' => 3, 'system_key' => 'valves', 'granted_by' => 'demo']);
        $db->table('control_unit.system_permissions')->insert(['user_id' => 5, 'system_key' => 'fleet', 'granted_by' => 'demo']);

        // ── Fleet ───────────────────────────────────────────────────────────
        $drivers = ['أحمد سالم', 'محمود عودة', 'خالد النجار', 'يوسف حمدان', 'سامي الخطيب', 'إياد شعت'];
        foreach ($drivers as $i => $name) {
            $id = 500 + $i;
            $db->table('g_store.beneficiaries')->insert(['oid' => $id, 'full_name' => $name, 'mobile_num' => '05990'.(12340 + $i), 'national_id' => (string) (400100200 + $i)]);
            $db->table('g_store.vehicle')->insert(['oid' => 700 + $i, 'plate_num' => (string) (31200 + $i * 17), 'vehicle_num' => ['شاحنة نفايات', 'صهريج مياه', 'بيك أب', 'جرافة', 'باص موظفين', 'سيارة صيانة'][$i], 'beneficiary_oid' => $id, 'odometer' => 40000 + $i * 913, 'hourly_price' => self::HOURLY_PRICES[$i]]);
            $db->table('g_store.vehicle_stages')->insert(['vehicle_oid' => 700 + $i, 'stage_oid' => 10, 'access_type' => 3]);
        }
        $db->table('tracking.user_station')->insert(['stage_oid' => 10, 'user_oid' => 100]);
        $db->table('tracking.user_station')->insert(['stage_oid' => 10, 'user_oid' => 104]);
        $db->table('mog_users.users_list')->insert(['id' => 4, 'emp_id' => 9004, 'full_name' => 'مساعد إداري تجريبي', 'tracking_id' => 103, 'is_active' => 1]);
        $db->table('g_store.vehicle_stages')->insert(['vehicle_oid' => 700, 'stage_oid' => 20, 'access_type' => 1]);
        $db->table('tracking.user_station')->insert(['stage_oid' => 20, 'user_oid' => 103]);

        foreach (['مكب جحر الديك', 'محطة ضخ الشيخ رضوان', 'مقر البلدية', 'مخازن البلدية', 'بئر الصفطاوي', 'حي الرمال'] as $i => $name) {
            $db->table('mission_locations')->insert(['name' => $name, 'category' => 'both', 'sort_order' => $i, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['جمع نفايات', 'نقل مياه', 'صيانة شبكة', 'مهمة إدارية'] as $i => $name) {
            $db->table('mission_types')->insert(['name' => $name, 'sort_order' => $i, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $plan = [
            ...array_fill(0, 11, ['confirmed', 'pending']),
            ...array_fill(0, 3, ['confirmed', 'needs_clarification']),
            ...array_fill(0, 2, ['confirmed', 'needs_clarification', 'responded']),
            ...array_fill(0, 9, ['confirmed', 'approved']),
            ...array_fill(0, 3, ['confirmed', 'rejected']),
            ...array_fill(0, 4, ['uploaded', 'pending', 'active']),
            ...array_fill(0, 2, ['confirmed', 'pending', 'experimental']),
        ];
        $places = ['مكب جحر الديك', 'محطة ضخ الشيخ رضوان', 'مقر البلدية', 'مخازن البلدية', 'بئر الصفطاوي', 'حي الرمال'];
        foreach ($plan as $n => $row) {
            $d = $n % count($drivers);
            $active = ($row[2] ?? null) === 'active';
            $start = $active ? $now->subMinutes(20 + $n * 3) : $now->subHours(3 + $n * 5);
            $end = $active ? null : $start->addMinutes(35 + ($n * 7) % 70);
            $points = match ($row[2] ?? null) {
                'experimental' => 23, 'active' => 60 + $n, default => 70 + ($n * 13) % 90
            };
            $distance = round($points * 0.06, 3);
            $tripId = $db->table('trips')->insertGetId([
                'local_id' => sprintf('demo-%04d', $n),
                'oracle_driver_id' => (string) (500 + $d), 'driver_name' => $drivers[$d], 'driver_phone' => '05990'.(12340 + $d), 'driver_employee_number' => (string) (400100200 + $d),
                'oracle_vehicle_id' => (string) (700 + $d), 'vehicle_plate' => (string) (31200 + $d * 17), 'vehicle_name' => ['شاحنة نفايات', 'صهريج مياه', 'بيك أب', 'جرافة', 'باص موظفين', 'سيارة صيانة'][$d],
                'origin_text' => $places[$n % 6], 'destination_text' => $places[($n + 2) % 6], 'mission_type' => ['جمع نفايات', 'نقل مياه', 'صيانة شبكة', 'مهمة إدارية'][$n % 4],
                'status' => $row[0], 'review_status' => $row[1], 'entry_source' => 'mobile', 'quality_version' => 2,
                // Price frozen at approval; every fifth approved trip predates capture (falls back to the current price).
                'hourly_price' => $row[1] === 'approved' && $n % 5 !== 0 ? self::HOURLY_PRICES[$d] : null,
                'started_at' => $start, 'ended_at' => $end, 'confirmed_at' => $end,
                'points_expected' => $points, 'points_received' => $points,
                'distance_km' => $active ? null : $distance, 'duration_minutes' => $end ? $start->diffInMinutes($end) : null,
                'virtual_fuel_liters' => $active ? null : round($distance * 0.12, 3), 'wait_minutes' => $n % 3 * 4,
                'review_note' => in_array($row[1], ['needs_clarification', 'rejected'], true) ? 'المسار يبتعد عن الوجهة المسجلة، يرجى التوضيح.' : null,
                'clarification_response' => ($row[2] ?? null) === 'responded' ? 'تم تحويل المسار بسبب إغلاق الطريق الرئيسي.' : null,
                'clarification_responded_at' => ($row[2] ?? null) === 'responded' ? $start->addHours(2) : null,
                'reviewed_at' => in_array($row[1], ['approved', 'rejected', 'needs_clarification'], true) ? $start->addHour() : null,
                'reviewed_by' => in_array($row[1], ['approved', 'rejected', 'needs_clarification'], true) ? (string) self::ADMIN_ID : null,
                'created_at' => $start, 'updated_at' => $end ?? $now,
            ]);

            $rows = [];
            // Trips with a tracking event also get a 4-minute GPS silence mid-route (gap estimate demo).
            $gapTrip = $n % 5 === 1;
            for ($i = 0; $i < $points; $i++) {
                $at = $start->addSeconds($i * 10 + ($gapTrip && $i >= intdiv($points, 2) ? 240 : 0));
                $rows[] = ['trip_id' => $tripId, 'sequence_number' => $i + 1,
                    'latitude' => 31.5017 + ($d * 0.004) + $i * 0.00042 + sin($i / 9) * 0.0006,
                    'longitude' => 34.4668 - ($d * 0.003) + $i * 0.00031 + cos($i / 11) * 0.0005,
                    'accuracy' => $gapTrip && $i === 5 ? 85 : 6 + ($i % 5), 'speed' => 8 + ($i % 7), 'client_is_valid' => 1,
                    'captured_at' => $at, 'recorded_at' => $active && $n % 2 === 0 ? $now->subSeconds(($points - $i) * 2) : $at];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                $db->table('trip_points')->insert($chunk);
            }

            if ($n % 5 === 1) {
                $db->table('trip_tracking_events')->insert(['trip_id' => $tripId, 'event_type' => $n % 2 ? 'gps_disabled' : 'tracking_gap', 'detected_at' => $start->addMinutes(12)]);
            }
            if ($n % 4 === 0) {
                $db->table('trip_stops')->insert(['trip_id' => $tripId, 'started_at' => $start->addMinutes(8), 'ended_at' => $start->addMinutes(14), 'latitude' => 31.505 + $d * 0.004, 'longitude' => 34.469, 'source' => 'automatic']);
            }
            if (in_array($row[1], ['approved', 'rejected', 'needs_clarification'], true)) {
                $db->table('audit_logs')->insert(['event_type' => 'trip.reviewed', 'entity_type' => 'trip', 'entity_id' => $tripId, 'actor' => (string) self::ADMIN_ID,
                    'payload' => json_encode(['review_status' => $row[1], 'note' => null, 'actor_name' => 'مستخدم العرض المحلي'], JSON_UNESCAPED_UNICODE), 'created_at' => $start->addHour(), 'updated_at' => $start->addHour()]);
            }
        }

        foreach ([['pending', 'أحمد العلمي'], ['pending', 'رنا عيسى'], ['approved', 'أحمد العلمي'], ['draft', 'رنا عيسى']] as $i => [$status, $by]) {
            $start = $now->subDays($i + 1)->setTime(8 + $i, 15);
            $db->table('trips')->insert([
                'oracle_driver_id' => (string) (500 + $i), 'driver_name' => $drivers[$i], 'oracle_vehicle_id' => (string) (700 + $i), 'vehicle_plate' => (string) (31200 + $i * 17), 'vehicle_name' => 'مركبة بدون تطبيق',
                'origin_text' => $places[$i], 'destination_text' => $places[$i + 1], 'mission_type' => 'مهمة إدارية', 'status' => 'confirmed', 'review_status' => $status,
                'entry_source' => 'manual', 'entered_by_user_id' => 50 + $i, 'entered_by_name' => $by, 'started_at' => $start, 'ended_at' => $start->addMinutes(95),
                'distance_km' => 12.5 + $i, 'points_expected' => 0, 'points_received' => 0, 'notes' => 'إدخال يدوي من المساعد الإداري.', 'created_at' => $start, 'updated_at' => $start,
            ]);
        }

        foreach ([[500, 42, 'com.android.vending'], [501, 42, null], [502, 41, 'com.android.vending'], [503, 40, null], [504, 42, 'com.huawei.appmarket'], [505, 38, null]] as $i => [$driver, $build, $store]) {
            $db->table('driver_app_installations')->insert([
                'oracle_driver_id' => (string) $driver, 'device_id' => sprintf('device-%02d', $i), 'driver_name' => $drivers[$i], 'driver_phone' => '05990'.(12340 + $i),
                'app_version' => '1.1.'.($build - 23), 'build_number' => $build, 'package_name' => 'ps.mogaza.driver', 'installer_store' => $store,
                'first_seen_at' => $now->subDays(40), 'last_seen_at' => $now->subDays($i * 4)->subHours($i), 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ── Wells ───────────────────────────────────────────────────────────
        foreach (['بئر الصفطاوي', 'بئر الشيخ رضوان 3', 'بئر الرمال الشمالي', 'بئر تل الهوى', 'بئر الشجاعية 2', 'بئر الزيتون', 'بئر النصر', 'بئر الشاطئ', 'بئر الدرج', 'بئر التفاح', 'بئر الصبرة', 'بئر الشيخ عجلين'] as $i => $name) {
            $db->table('wells')->insert(['well_num' => 'W-'.str_pad((string) (101 + $i), 3, '0', STR_PAD_LEFT), 'name' => $name,
                'damage_status' => ['سليمة', 'متضررة', 'أعيد تأهيلها', 'مدمرة'][$i % 4], 'operational_status' => $i % 4 === 3 ? 'متوقف' : 'يعمل',
                'pump_type' => $i % 2 ? 'vertical' : 'submersible', 'power_source' => ['generator', 'electricity', 'solar'][$i % 3], 'capacity' => 60 + $i * 5,
                'is_active' => $i < 10 ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['فريق الشمال', 'فريق الوسط', 'فريق الجنوب'] as $i => $name) {
            $db->table('teams')->insert(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['محمد أبو شمالة', 'علاء حلس', 'فادي الغول', 'نبيل عياد'] as $i => $name) {
            $db->table('inspectors')->insert(['name' => $name, 'phone' => '05970'.(55500 + $i), 'is_active' => $i < 3 ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('inspector_teams')->insert(['inspector_oid' => $i + 1, 'team_oid' => $i % 3 + 1]);
        }
        for ($w = 1; $w <= 12; $w++) {
            $db->table('team_wells')->insert(['team_oid' => $w % 3 + 1, 'well_oid' => $w]);
        }
        $oid = 0;
        for ($day = 6; $day >= 0; $day--) {
            for ($w = 1; $w <= 10; $w++) {
                if ($day === 0 && $w > 6) {
                    continue; // four active wells still missing today's reading
                }
                $date = $now->subDays($day)->setTime(7 + $w % 5, 10 * ($w % 6));
                $db->table('well_readings')->insert(['well_oid' => $w, 'inspector_oid' => $w % 3 + 1, 'generator_hours_reading' => 1200 + $w * 90 + (6 - $day) * (9 + $w % 4),
                    'pump_hours_reading' => 900 + $w * 70 + (6 - $day) * 8, 'chlorine' => 0.4 + ($w % 3) / 10, 'salinity' => 900 + $w * 37, 'quality' => ['excellent', 'good', 'acceptable'][$w % 3],
                    'reading_date' => $date, 'created_at' => $date->addMinutes(40), 'updated_at' => $date->addMinutes(40)]);
                $oid++;
            }
        }
        foreach ([['عطل', 'danger', '#e5484d'], ['نقص سولار', 'fuel', '#f5a524'], ['ملاحظة', 'info', '#3e63dd']] as $i => [$name, $icon, $color]) {
            $db->table('notification_types')->insert(['name' => $name, 'icon' => $icon, 'emoji' => $icon, 'color' => $color, 'priority' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([[2, 1, 'المولد لا يعمل منذ الصباح، تم إبلاغ الصيانة.', 0], [5, 2, 'كمية السولار في الخزان تكفي ليوم واحد فقط.', 0], [7, 1, 'تسريب في خط التبريد.', 0], [3, 3, 'تم تنظيف غرفة البئر.', 1], [9, 2, 'تأخر وصول صهريج السولار.', 0], [1, 3, 'قراءة العداد غير واضحة بسبب الغبار.', 1]] as $i => [$well, $type, $msg, $read]) {
            $at = $now->subHours($i * 5 + 1);
            $db->table('well_notifications')->insert(['well_oid' => $well, 'notification_type_oid' => $type, 'inspector_oid' => $i % 3 + 1, 'message' => $msg, 'is_read' => $read, 'read_at' => $read ? $at : null, 'created_at' => $at, 'updated_at' => $at]);
        }

        // Diesel: quarters, generators, tank shapes, approved fuel deliveries and field inventories.
        foreach (['الرمال الشمالي', 'الشيخ رضوان', 'تل الهوى', 'الشجاعية', 'الزيتون'] as $i => $name) {
            $db->table('gazadata.quarters')->insert(['oid' => $i + 1, 'name' => $name]);
        }
        for ($w = 1; $w <= 12; $w++) {
            $db->table('wells')->where('oid', $w)->update([
                'quarter_oid' => $w % 5 + 1, 'well_num' => 'W-'.(100 + $w),
                'latitude' => 31.49 + ($w % 6) * 0.012, 'longitude' => 34.43 + intdiv($w, 2) * 0.008,
                'tank_shape' => $w <= 4 ? 'Daily Tank' : ($w <= 9 ? 'اسطواني عمودي' : null), 'tank_diameter' => 1.2, 'tank_height' => 1.6,
            ]);
            $db->table('g_store.generator')->insert(['oid' => 900 + $w, 'name' => 'مولد '.(150 + $w * 10).' KVA', 'generator_num' => 'G-'.(40 + $w), 'engine_capacity' => 150 + $w * 10, 'fuel_consumption' => 18 + $w]);
            $db->table('well_generators')->insert(['well_oid' => $w, 'generator_oid' => 900 + $w, 'fuel_consumption_lph' => 18 + $w, 'consumption_tolerance_lph' => 2]);
        }
        $logId = 0;
        for ($w = 1; $w <= 9; $w++) {
            for ($k = 0; $k < 4; $k++) {
                $logId++;
                $at = $now->subDays(28 - $k * 7)->setTime(9, 0);
                $db->table('g_store.fuel_log')->insert(['oid' => $logId, 'status_oid' => 416, 'fill_up_date' => $at, 'entry_date' => $at->addHour()]);
                $db->table('g_store.fuel_log_details')->insert(['oid' => $logId, 'fuel_log_oid' => $logId, 'asset_oid' => 900 + $w, 'asset_type' => 401,
                    'current_reading' => (string) (2000 + $w * 50 + $k * (120 + $w * 3)), 'gallons' => 380 + $w * 10, 'approved_gallons' => 360 + $w * 10 + ($w === 3 && $k === 3 ? 900 : 0)]);
            }
        }
        for ($w = 5; $w <= 9; $w++) {
            for ($k = 0; $k < 3; $k++) {
                $at = $now->subDays(24 - $k * 8)->setTime(11, 30);
                $db->table('diesel_inventories')->insert(['well_oid' => $w, 'inspector_oid' => $w % 3 + 1, 'generator_oid' => 900 + $w, 'inventory_date' => $at,
                    'generator_hours_reading' => 2000 + $w * 50 + $k * (120 + $w * 3) + 30, 'diesel_height_cm' => 140 - $k * 25 + ($k === 2 ? 40 : 0),
                    'available_fuel_liters' => 1000, 'created_at' => $at->addMinutes(20), 'updated_at' => $at->addMinutes(20)]);
            }
        }

        // ── Valves ──────────────────────────────────────────────────────────
        for ($v = 1; $v <= 40; $v++) {
            $db->table('eme.water_valves')->insert(['w4_general_id' => 3000 + $v, 'w4_street_no' => 100 + $v % 23, 'w4_valve_type' => $v % 2 ? 'Gate' : 'Butterfly', 'w4_valve_diameter' => [4, 6, 8, 12][$v % 4], 'w4_status' => 'ACTIVE', 'w4_source' => ['الرمال', 'الشيخ رضوان', 'الزيتون'][$v % 3],
                'fixture_latitude' => 31.495 + ($v % 8) * 0.006, 'fixture_longitude' => 34.44 + intdiv($v, 8) * 0.009]);
        }
        foreach (['فريق محابس الرمال', 'فريق محابس الشرق'] as $i => $name) {
            $db->table('valve_teams')->insert(['name' => $name, 'assign_all_valves' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
        for ($v = 1; $v <= 15; $v++) {
            $db->table('valve_team_valves')->insert(['team_oid' => 1, 'valve_oid' => 3000 + $v]);
        }
        foreach (['حسام مقداد', 'رائد البيطار', 'وسيم عاشور'] as $i => $name) {
            $db->table('valve_inspectors')->insert(['name' => $name, 'phone' => '05980'.(66600 + $i), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('valve_inspector_teams')->insert(['inspector_oid' => $i + 1, 'team_oid' => $i % 2 + 1]);
        }
        for ($l = 0; $l < 70; $l++) {
            $at = $now->subHours($l * 3 + 1);
            $db->table('valve_logs')->insert(['valve_oid' => 3001 + $l % 40, 'user_oid' => $l % 3 + 1, 'action' => ['open', 'close', 'open', 'maintenance', 'close', 'replacement'][$l % 6], 'note' => $l % 4 === 0 ? 'تم حسب جدول التوزيع.' : null, 'action_at' => $at, 'created_at' => $at, 'updated_at' => $at]);
        }
        foreach ([['pending', 'high', 'المحبس لا يغلق بالكامل.'], ['pending', 'medium', 'تسريب حول جسم المحبس.'], ['pending', 'low', 'غطاء غرفة المحبس مكسور.'], ['in_progress', 'high', 'استبدال حشوة المحبس.'], ['in_progress', 'medium', 'صعوبة في الوصول للمحبس بسبب الركام.'], ['resolved', 'low', 'تم تنظيف غرفة المحبس.']] as $i => [$status, $priority, $desc]) {
            $at = $now->subHours($i * 7 + 2);
            $db->table('valve_maintenance_reports')->insert(['valve_oid' => 3002 + $i * 3, 'reported_by' => $i % 3 + 1, 'description' => $desc, 'priority' => $priority, 'status' => $status, 'resolved_at' => $status === 'resolved' ? $at->addHours(3) : null, 'created_at' => $at, 'updated_at' => $at]);
        }
    }
}
