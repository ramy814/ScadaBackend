<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Fleet\FleetModule;
use App\Modules\Fleet\Services\FleetAccessService;
use App\Platform\Access\AccessService;
use App\Platform\Applications\ApplicationRegistry;
use App\Platform\Identity\MobilePrincipals;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ApplicationRegistry::class);
        // Per-request memoization of permission and Oracle vehicle-scope lookups.
        $this->app->scoped(AccessService::class);
        $this->app->scoped(FleetAccessService::class);
        $this->app->scoped(FleetModule::class);
    }

    public function boot(): void
    {
        // Mobile tokens keep their legacy TOKENABLE_TYPE values (see MobilePrincipals).
        Relation::morphMap(MobilePrincipals::LEGACY_MORPH_TYPES);

        // SQLite fixture: emulate Oracle schemas (g_store.VEHICLE, mog_users.users_list, ...)
        // by attaching one database per schema. Never active against real Oracle.
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $connection = $event->connection;
            $schemas = $connection->getConfig('attach_schemas') ?? [];
            if ($connection->getDriverName() !== 'sqlite' || $schemas === []) {
                return;
            }

            // Oracle date literal helper used by GeneratorRepository; the fixture stores ISO strings.
            $connection->getPdo()->sqliteCreateFunction('TO_DATE', fn ($value) => $value, 2);

            $database = (string) $connection->getConfig('database');
            foreach ($schemas as $schema) {
                $path = $database === ':memory:'
                    ? ':memory:'
                    : preg_replace('/\.sqlite$/', ".{$schema}.sqlite", $database);
                $connection->statement("ATTACH DATABASE ? AS {$schema}", [$path]);
            }
        });
    }
}
