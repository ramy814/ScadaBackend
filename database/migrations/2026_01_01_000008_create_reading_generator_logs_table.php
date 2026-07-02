<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_generator_logs', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('READING_OID')->constrained('well_readings', 'OID')->cascadeOnDelete();
            $table->integer('GENERATOR_OID');
            $table->decimal('CURRENT_READING', 12, 2);
            $table->decimal('GALLONS', 12, 2);
            $table->timestamp('CREATED_AT')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_generator_logs');
    }
};
