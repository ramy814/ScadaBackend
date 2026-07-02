<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valve_maintenance_reports', function (Blueprint $table) {
            $table->id('OID');
            $table->unsignedBigInteger('VALVE_OID')->index();
            $table->unsignedBigInteger('REPORTED_BY');
            $table->text('DESCRIPTION');
            $table->enum('PRIORITY', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('STATUS', ['pending', 'in_progress', 'resolved'])->default('pending');
            $table->unsignedBigInteger('RESOLVED_BY')->nullable();
            $table->timestamp('RESOLVED_AT')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valve_maintenance_reports');
    }
};
