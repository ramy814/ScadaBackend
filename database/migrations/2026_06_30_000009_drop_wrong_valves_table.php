<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'oracle') {
            Schema::dropIfExists('valves');

            return;
        }

        try {
            $connection->statement('DROP TABLE VALVES CASCADE CONSTRAINTS');
        } catch (Throwable $exception) {
            if (! str_contains($exception->getMessage(), 'ORA-00942')) {
                throw $exception;
            }
        }
    }

    public function down(): void
    {
        // The local VALVES table was created by mistake. Source data lives in EME.WATER_VALVES.
    }
};
