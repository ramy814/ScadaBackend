<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('local_id')->unique();
            $table->string('oracle_driver_id')->index();
            $table->string('driver_name');
            $table->string('driver_phone')->nullable();
            $table->string('driver_employee_number')->nullable();
            $table->string('oracle_vehicle_id')->nullable()->index();
            $table->string('vehicle_plate')->index();
            $table->string('vehicle_name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('status')->default('uploaded')->index();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('origin_type')->default('free_text');
            $table->string('origin_id')->nullable();
            $table->string('origin_text');
            $table->string('destination_type')->default('free_text');
            $table->string('destination_id')->nullable();
            $table->string('destination_text');
            $table->string('mission_type')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('start_odometer')->nullable();
            $table->unsignedInteger('end_odometer')->nullable();
            $table->unsignedInteger('points_expected')->default(0);
            $table->unsignedInteger('points_received')->default(0);
            $table->string('client_checksum')->nullable();
            $table->string('server_checksum')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('virtual_fuel_liters', 10, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('trip_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence_number');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 2)->nullable();
            $table->decimal('heading', 8, 2)->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->string('provider')->nullable();
            $table->timestamp('captured_at')->index();
            $table->unique(['trip_id', 'sequence_number']);
        });

        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('employee_id')->nullable()->index();
            $table->string('name');
            $table->string('source')->default('free_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_passengers');
        Schema::dropIfExists('trip_stops');
        Schema::dropIfExists('trip_points');
        Schema::dropIfExists('trips');
    }
};
