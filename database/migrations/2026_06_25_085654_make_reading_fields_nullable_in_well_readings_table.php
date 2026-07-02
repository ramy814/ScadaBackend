<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('well_readings', function (Blueprint $table) {
            $table->decimal('CHLORINE', 10, 2)->nullable()->change();
            $table->decimal('SALINITY', 10, 2)->nullable()->change();
            $table->string('QUALITY')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('well_readings', function (Blueprint $table) {
            $table->decimal('CHLORINE', 10, 2)->nullable(false)->change();
            $table->decimal('SALINITY', 10, 2)->nullable(false)->change();
            $table->string('QUALITY')->nullable(false)->change();
        });
    }
};
