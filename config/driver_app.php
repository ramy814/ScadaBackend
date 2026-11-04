<?php

return [
    'latest_version' => env('DRIVER_APP_LATEST_VERSION', '1.1.19'),
    'latest_build' => (int) env('DRIVER_APP_LATEST_BUILD', 42),
    'recent_days' => (int) env('DRIVER_APP_RECENT_DAYS', 14),
];
