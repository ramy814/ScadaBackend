<?php

declare(strict_types=1);

namespace App\Platform\Support;

/**
 * Settings the platform needs whatever config/database.php, config/filesystems.php and
 * config/app.php the server has. Those files are server-local (ignored since the legacy
 * backend), so production keeps its existing copies; values here apply only when missing.
 */
final class RuntimeConfig
{
    public static function apply(): void
    {
        self::oracle();
        self::disks();
    }

    private static function oracle(): void
    {
        if (env('ORACLE_DRIVER', 'oracle') === 'sqlite') {
            // Local fixture only (tests and `php artisan platform:demo`): Oracle schemas are
            // emulated with attached SQLite databases (see AppServiceProvider).
            config(['database.connections.oracle' => [
                'driver' => 'sqlite',
                'database' => env('ORACLE_SQLITE_DATABASE', database_path('platform-demo.sqlite')),
                'prefix' => '',
                'foreign_key_constraints' => false,
                'attach_schemas' => ['g_store', 'mog_users', 'tracking', 'eme', 'control_unit', 'gazadata', 'dss_core'],
            ]]);

            return;
        }

        if (config('database.connections.oracle') !== null) {
            return; // production: the server's own Oracle connection, exactly as legacy used it
        }

        config(['database.connections.oracle' => [
            'driver' => 'oracle',
            // DB_ORACLE_* first; otherwise the legacy backend's names (yajra defaults).
            'tns' => env('DB_ORACLE_TNS') ?: env('DB_TNS', ''),
            'host' => env('DB_ORACLE_HOST') ?: env('DB_HOST', ''),
            'port' => env('DB_ORACLE_PORT') ?: env('DB_PORT', '1521'),
            'database' => env('DB_ORACLE_DATABASE') ?: env('DB_DATABASE', ''),
            'service_name' => env('DB_ORACLE_SERVICE_NAME') ?: env('DB_SERVICE_NAME', ''),
            'username' => env('DB_ORACLE_USERNAME') ?: env('DB_USERNAME', ''),
            'password' => env('DB_ORACLE_PASSWORD') ?: env('DB_PASSWORD', ''),
            'charset' => env('DB_ORACLE_CHARSET') ?: env('DB_CHARSET', 'AL32UTF8'),
            'prefix' => '',
            'prefix_schema' => env('DB_ORACLE_SCHEMA_PREFIX') ?: env('DB_SCHEMA_PREFIX', ''),
            'edition' => env('DB_ORACLE_EDITION') ?: env('DB_EDITION', 'ora$base'),
            'server_version' => env('DB_ORACLE_SERVER_VERSION') ?: env('DB_SERVER_VERSION', '11g'),
            'load_balance' => env('DB_ORACLE_LOAD_BALANCE') ?: env('DB_LOAD_BALANCE', 'yes'),
            'dynamic' => [],
        ]]);
    }

    private static function disks(): void
    {
        // Wells photos: the same storage/app/private files the legacy backend wrote.
        if (config('filesystems.disks.legacy_media') === null) {
            config(['filesystems.disks.legacy_media' => [
                'driver' => 'local',
                'root' => env('LEGACY_MEDIA_ROOT') ?: storage_path('app/private'),
                'throw' => false,
            ]]);
        }

        // Private files are never served by the framework's /storage route.
        config(['filesystems.disks.local.serve' => false]);
    }
}
