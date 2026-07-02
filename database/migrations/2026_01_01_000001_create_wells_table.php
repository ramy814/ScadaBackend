<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wells', function (Blueprint $table) {
            $table->id('OID');
            $table->string('NAME');
            $table->integer('QUARTER_OID')->nullable();
            $table->string('PUMP_TYPE')->comment('centrifugal or submersible');
            $table->decimal('CAPACITY', 10, 2);
            $table->string('POWER_SOURCE')->comment('electricity or solar');
            $table->decimal('LATITUDE', 10, 8);
            $table->decimal('LONGITUDE', 11, 8);
            $table->boolean('IS_ACTIVE')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wells');
    }
};
