<?php

use App\Modules\Fleet\FleetModule;
use App\Modules\Valves\ValvesModule;
use App\Modules\Wells\WellsModule;

/*
| Registry of the Flutter applications managed by this platform. Every fact here is
| taken from the legacy backend's routes, auth and config (see docs/01-audit.md).
| `key` must equal the CONTROL_UNIT.SYSTEM_PERMISSIONS system key.
*/

return [
    'fleet' => [
        'key' => 'fleet',
        'module' => FleetModule::class,
        'accent' => 'fleet',
        'icon' => 'truck',
        'home' => 'fleet.reviews',
        'name' => ['ar' => 'تطبيق السائق', 'en' => 'Driver app'],
        'domain' => ['ar' => 'الأسطول', 'en' => 'Fleet'],
        'audience' => ['ar' => 'سائقو مركبات البلدية', 'en' => 'Municipal vehicle drivers'],
        'platforms' => ['Android'],
        'auth' => ['ar' => 'رمز SMS ← توكن Sanctum بصلاحية driver', 'en' => 'SMS OTP → Sanctum token (driver ability)'],
        'api_prefix' => '/api/driver',
        'offline_first' => true,
        'release_tracking' => true,
    ],
    'wells' => [
        'key' => 'wells',
        'module' => WellsModule::class,
        'accent' => 'wells',
        'icon' => 'droplets',
        'home' => 'wells.today',
        'name' => ['ar' => 'تطبيق مفتشي الآبار', 'en' => 'Wells inspector app'],
        'domain' => ['ar' => 'الآبار', 'en' => 'Wells'],
        'audience' => ['ar' => 'مفتشو الآبار الميدانيون', 'en' => 'Field well inspectors'],
        'platforms' => ['Android'],
        'auth' => ['ar' => 'هاتف وكلمة مرور ← توكن بصلاحية wells', 'en' => 'Phone + password → token (wells ability)'],
        'api_prefix' => '/api/wells',
        'offline_first' => true,
        'release_tracking' => false,
    ],
    'valves' => [
        'key' => 'valves',
        'module' => ValvesModule::class,
        'accent' => 'valves',
        'icon' => 'gauge',
        'home' => 'valves.today',
        'name' => ['ar' => 'تطبيق SCADA للمحابس', 'en' => 'SCADA valves app'],
        'domain' => ['ar' => 'المحابس', 'en' => 'Valves'],
        'audience' => ['ar' => 'مفتشو المحابس', 'en' => 'Valve inspectors'],
        'platforms' => ['Android'],
        'auth' => ['ar' => 'هاتف وكلمة مرور ← توكن بصلاحية scada', 'en' => 'Phone + password → token (scada ability)'],
        'api_prefix' => '/api/valves',
        'offline_first' => true,
        'release_tracking' => false,
    ],
];
