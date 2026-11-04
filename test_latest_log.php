<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

App\Models\Valves\Valve::resolveRelationUsing('latestLog', function ($valve) {
    return $valve->hasOne(App\Models\Valves\ValveLog::class, 'VALVE_OID', 'w4_general_id')->latestOfMany('ACTION_AT');
});

$valves = App\Models\Valves\Valve::with('latestLog')->limit(5)->get();
dump($valves->map(fn($v) => ['id' => $v->oid, 'log' => $v->latestLog?->action])->toArray());
