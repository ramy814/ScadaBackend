<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('well_readings', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('WELL_OID')->constrained('wells', 'OID')->cascadeOnDelete();
            $table->foreignId('INSPECTOR_OID')->constrained('inspectors', 'OID')->cascadeOnDelete();
            $table->decimal('FLOW_RATE', 10, 2)->comment('م٣/ساعة');
            $table->decimal('CHLORINE', 10, 2)->comment('لتر');
            $table->decimal('SALINITY', 10, 2);
            $table->string('QUALITY')->comment('excellent, good, acceptable, poor');
            $table->timestamp('READING_DATE');
            $table->decimal('LATITUDE', 10, 8)->nullable();
            $table->decimal('LONGITUDE', 11, 8)->nullable();
            $table->timestamp('SYNCED_AT')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_readings');
    }
};
