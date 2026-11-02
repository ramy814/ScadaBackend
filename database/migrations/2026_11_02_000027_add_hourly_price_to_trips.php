<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سعر ساعة المركبة (G_STORE.VEHICLE.HOURLY_PRICE) يُثبَّت على الرحلة لحظة اعتمادها،
 * حتى لا يتغير مبلغ المطالبة المالية لفترة سابقة إذا تغيّر سعر المركبة لاحقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->decimal('hourly_price', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn('hourly_price');
        });
    }
};
