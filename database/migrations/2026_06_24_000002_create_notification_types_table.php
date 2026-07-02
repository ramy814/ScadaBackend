<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_types', function (Blueprint $table) {
            $table->id('OID');
            $table->string('NAME');
            $table->string('ICON')->default('bell');
            $table->string('COLOR')->default('#3b82f6')->nullable();
            $table->string('EMOJI')->default('🔔')->nullable();
            $table->integer('PRIORITY')->default(1)->comment('1 = أعلى أولوية');
            $table->boolean('IS_ACTIVE')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_types');
    }
};
