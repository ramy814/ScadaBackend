<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Fixture\DemoData;
use Database\Fixture\FixtureSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Builds the local SQLite fixture with demonstration data. Local only. */
class DemoCommand extends Command
{
    protected $signature = 'platform:demo';

    protected $description = 'Create the local SQLite fixture (Oracle schema mirror) with demonstration data';

    public function handle(): int
    {
        if (! app()->environment('local') || config('database.connections.oracle.driver') !== 'sqlite') {
            $this->error('Refusing: requires APP_ENV=local and ORACLE_DRIVER=sqlite.');

            return self::FAILURE;
        }

        $main = (string) config('database.connections.oracle.database');
        DB::purge('oracle');
        foreach (glob(preg_replace('/\.sqlite$/', '', $main).'*.sqlite') ?: [] as $file) {
            unlink($file);
        }
        touch($main);

        FixtureSchema::create();
        DemoData::seed();

        $this->info('Demo fixture ready. Set MOG_LOCAL_ADMIN_USER_ID='.DemoData::ADMIN_ID.' and open /dev-login.');

        return self::SUCCESS;
    }
}
