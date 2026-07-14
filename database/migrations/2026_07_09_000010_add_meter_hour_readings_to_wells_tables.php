<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('well_readings', 'GENERATOR_HOURS_READING')) {
            Schema::table('well_readings', function (Blueprint $table) {
                $table->decimal('GENERATOR_HOURS_READING', 10, 2)->nullable()->after('FLOW_RATE');
            });
        }

        if (! Schema::hasColumn('well_readings', 'PUMP_HOURS_READING')) {
            Schema::table('well_readings', function (Blueprint $table) {
                $table->decimal('PUMP_HOURS_READING', 10, 2)->nullable()->after('GENERATOR_HOURS_READING');
            });
        }

        DB::connection('oracle')->statement(
            'ALTER TABLE DIESEL_INVENTORIES MODIFY (AVAILABLE_FUEL_LITERS NULL)'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('well_readings', 'GENERATOR_HOURS_READING')) {
            Schema::table('well_readings', function (Blueprint $table) {
                $table->dropColumn('GENERATOR_HOURS_READING');
            });
        }

        if (Schema::hasColumn('well_readings', 'PUMP_HOURS_READING')) {
            Schema::table('well_readings', function (Blueprint $table) {
                $table->dropColumn('PUMP_HOURS_READING');
            });
        }

        DB::connection('oracle')->statement(
            'ALTER TABLE DIESEL_INVENTORIES MODIFY (AVAILABLE_FUEL_LITERS NOT NULL)'
        );
    }
};
