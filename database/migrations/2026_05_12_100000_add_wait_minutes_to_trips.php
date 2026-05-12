<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('trips', 'wait_minutes')) {
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedSmallInteger('wait_minutes')->nullable()->after('end_odometer');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('trips', 'wait_minutes')) {
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('wait_minutes');
        });
    }
};
