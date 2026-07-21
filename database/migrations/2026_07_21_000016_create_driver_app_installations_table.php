<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_app_installations', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('oracle_driver_id', 64);
            $table->string('device_id', 64);
            $table->string('driver_name')->nullable();
            $table->string('driver_phone', 40)->nullable();
            $table->string('driver_employee_number', 120)->nullable();
            $table->string('app_version', 32);
            $table->unsignedInteger('build_number');
            $table->string('package_name', 191);
            $table->string('installer_store', 191)->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('app_updated_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(
                ['oracle_driver_id', 'device_id'],
                'dai_driver_device_uq',
            );
            $table->index('last_seen_at', 'dai_last_seen_ix');
            $table->index('build_number', 'dai_build_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_app_installations');
    }
};
