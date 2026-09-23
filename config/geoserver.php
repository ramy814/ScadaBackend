<?php

$value = static function (string $key, string $default): string {
    if (function_exists('env')) {
        $configured = (string) env($key, '');

        return $configured !== '' ? $configured : $default;
    }

    return getenv($key) ?: $default;
};

return [
    'base_url' => $value('GEOSERVER_BASE_URL', 'https://geo.mogaza.org/geoserver'),
    'username' => $value('GEOSERVER_USERNAME', 'mobile_apps'),
    'password' => $value('GEOSERVER_PASSWORD', 'v0ypiw28WS51'),
];
