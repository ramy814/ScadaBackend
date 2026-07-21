<?php

return [
    'latest_version' => env('DRIVER_APP_LATEST_VERSION', '1.0.20'),
    'latest_build' => (int) env('DRIVER_APP_LATEST_BUILD', 21),
    'recent_days' => (int) env('DRIVER_APP_RECENT_DAYS', 14),
];
