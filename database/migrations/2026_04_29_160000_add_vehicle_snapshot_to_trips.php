<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'oracle_vehicle_id')) {
                $table->string('oracle_vehicle_id')->nullable()->index();
            }

            if (! Schema::hasColumn('trips', 'vehicle_plate')) {
                $table->string('vehicle_plate')->nullable()->index();
            }

            if (! Schema::hasColumn('trips', 'vehicle_name')) {
                $table->string('vehicle_name')->nullable();
            }

            if (! Schema::hasColumn('trips', 'vehicle_type')) {
                $table->string('vehicle_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'vehicle_type')) {
                $table->dropColumn('vehicle_type');
            }

            if (Schema::hasColumn('trips', 'vehicle_name')) {
                $table->dropColumn('vehicle_name');
            }

            if (Schema::hasColumn('trips', 'vehicle_plate')) {
                $table->dropColumn('vehicle_plate');
            }

            if (Schema::hasColumn('trips', 'oracle_vehicle_id')) {
                $table->dropColumn('oracle_vehicle_id');
            }
        });
    }
};
