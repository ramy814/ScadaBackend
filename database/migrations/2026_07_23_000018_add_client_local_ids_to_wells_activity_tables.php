<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('well_readings', function (Blueprint $table): void {
            $table->string('CLIENT_LOCAL_ID', 64)->nullable();
            $table->unique('CLIENT_LOCAL_ID', 'WREAD_CLIENT_LOCAL_UK');
        });

        Schema::table('well_notifications', function (Blueprint $table): void {
            $table->string('CLIENT_LOCAL_ID', 64)->nullable();
            $table->unique('CLIENT_LOCAL_ID', 'WNOTIF_CLIENT_LOCAL_UK');
        });

        Schema::table('diesel_inventories', function (Blueprint $table): void {
            $table->string('CLIENT_LOCAL_ID', 64)->nullable();
            $table->unique('CLIENT_LOCAL_ID', 'WDIESEL_CLIENT_LOCAL_UK');
        });
    }

    public function down(): void
    {
        Schema::table('well_readings', function (Blueprint $table): void {
            $table->dropUnique('WREAD_CLIENT_LOCAL_UK');
            $table->dropColumn('CLIENT_LOCAL_ID');
        });

        Schema::table('well_notifications', function (Blueprint $table): void {
            $table->dropUnique('WNOTIF_CLIENT_LOCAL_UK');
            $table->dropColumn('CLIENT_LOCAL_ID');
        });

        Schema::table('diesel_inventories', function (Blueprint $table): void {
            $table->dropUnique('WDIESEL_CLIENT_LOCAL_UK');
            $table->dropColumn('CLIENT_LOCAL_ID');
        });
    }
};
