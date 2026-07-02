<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valve_logs', function (Blueprint $table) {
            $table->id('OID');
            $table->unsignedBigInteger('VALVE_OID')->index();
            $table->unsignedBigInteger('USER_OID');
            $table->enum('ACTION', ['open', 'close', 'maintenance', 'replacement']);
            $table->text('NOTE')->nullable();
            $table->text('SMS_RAW')->nullable();
            $table->timestamp('ACTION_AT')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valve_logs');
    }
};
