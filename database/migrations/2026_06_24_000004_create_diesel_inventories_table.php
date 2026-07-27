<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diesel_inventories', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('WELL_OID')->constrained('wells', 'OID');
            $table->foreignId('INSPECTOR_OID')->constrained('inspectors', 'OID');
            $table->integer('GENERATOR_OID')->nullable()->comment('References G_STORE.GENERATOR');
            $table->timestamp('INVENTORY_DATE');
            $table->decimal('GENERATOR_HOURS_READING', 12, 2)->nullable()->comment('ساعات تشغيل المولد');
            $table->decimal('DIESEL_HEIGHT_CM', 10, 2)->nullable()->comment('ارتفاع السولار داخل الخزان بالسنتيمتر');
            $table->decimal('AVAILABLE_FUEL_LITERS', 12, 2)->comment('الرصيد الفعلي المتوفر باللتر');
            $table->decimal('LATITUDE', 10, 8)->nullable();
            $table->decimal('LONGITUDE', 11, 8)->nullable();
            $table->text('NOTES')->nullable();
            $table->timestamp('SYNCED_AT')->nullable();
            $table->timestamps();

            $table->index(['WELL_OID', 'INVENTORY_DATE']);
            $table->index(['GENERATOR_OID', 'INVENTORY_DATE']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diesel_inventories');
    }
};
