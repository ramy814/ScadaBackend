<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'oracle_driver_id')) {
                $table->string('oracle_driver_id')->nullable()->index();
            }

            if (! Schema::hasColumn('trips', 'driver_name')) {
                $table->string('driver_name')->nullable();
            }

            if (! Schema::hasColumn('trips', 'driver_phone')) {
                $table->string('driver_phone')->nullable();
            }

            if (! Schema::hasColumn('trips', 'driver_employee_number')) {
                $table->string('driver_employee_number')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'driver_employee_number')) {
                $table->dropColumn('driver_employee_number');
            }

            if (Schema::hasColumn('trips', 'driver_phone')) {
                $table->dropColumn('driver_phone');
            }

            if (Schema::hasColumn('trips', 'driver_name')) {
                $table->dropColumn('driver_name');
            }

            if (Schema::hasColumn('trips', 'oracle_driver_id')) {
                $table->dropColumn('oracle_driver_id');
            }
        });
    }
};
