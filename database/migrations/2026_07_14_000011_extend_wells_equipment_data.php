<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->decimal('PUMP_POWER_HP', 10, 2)->nullable()->comment('قدرة المضخة بالحصان');
            $table->string('CHLORINATION_STATUS')->nullable()->comment('يعمل,لا يعمل');
            $table->string('COOLING_SYSTEM')->nullable()->comment('اوتوماتيك,يدوي,غير متوفر,غاطس,اوتوماتيك يحتاج صيانة');
            $table->string('COOLING_LINE_TYPE')->nullable()->comment('حديد سكب,HDPE');
        });

        Schema::create('well_media', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('WELL_OID')->constrained('wells', 'OID');
            $table->string('CATEGORY', 50);
            $table->string('LABEL')->nullable();
            $table->string('FILE_PATH', 500);
            $table->string('ORIGINAL_NAME');
            $table->string('MIME_TYPE', 100)->nullable();
            $table->timestamps();

            $table->index(['WELL_OID', 'CATEGORY']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_media');

        Schema::table('wells', function (Blueprint $table) {
            $table->dropColumn([
                'PUMP_POWER_HP',
                'CHLORINATION_STATUS',
                'COOLING_SYSTEM',
                'COOLING_LINE_TYPE',
            ]);
        });
    }
};
