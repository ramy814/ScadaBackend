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
            // البيانات العامة
            $table->string('WELL_NUM')->nullable()->after('OID');
            $table->string('DAMAGE_STATUS')->nullable()->comment('سليمة,متضررة,أعيد تأهيلها,مدمرة');
            $table->string('OPERATIONAL_STATUS')->nullable()->comment('يعمل,متوقف,تحت الصيانة');
            $table->date('INSTALLATION_DATE')->nullable();
            $table->date('LAST_MAINTENANCE_DATE')->nullable();

            // بيانات البئر والمضخة
            $table->decimal('WELL_DEPTH', 10, 2)->nullable()->comment('العمق بالمتر');
            $table->decimal('SALINITY', 10, 2)->nullable()->comment('الملوحة');

            // بيانات خزان الوقود
            $table->string('TANK_SHAPE')->nullable()->comment('اسطواني عمودي,اسطواني أفقي,مكعب');
            $table->string('TANK_MATERIAL')->nullable()->comment('بلاستيك مقوى,معدن,خرسانة');
            $table->decimal('TANK_DIAMETER', 10, 2)->nullable()->comment('القطر بالمتر');
            $table->decimal('TANK_HEIGHT', 10, 2)->nullable()->comment('الارتفاع بالمتر');

            // نظام الكلورة
            $table->string('CHLORINATION_SYSTEM')->nullable()->comment('لا يوجد نظام كلورة,كلور غاز,كلور سائل,كلور صلب');
            $table->decimal('CHLORINE_TANK_CAPACITY', 10, 2)->nullable()->comment('سعة خزان الكلور بالليتر');
            $table->string('CHLORINE_TANK_TYPE')->nullable();
            $table->string('CHLORINE_TANK_MATERIAL')->nullable();
            $table->string('CHLORINE_PUMP_MODEL')->nullable();
            $table->string('CHLORINE_PUMP_TYPE')->nullable();

            // خزان التبريد
            $table->decimal('COOLING_TANK_CAPACITY', 10, 2)->nullable()->comment('سعة خزان التبريد بالليتر');
            $table->string('COOLING_TANK_TYPE')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wells', function (Blueprint $table) {
            $table->dropColumn([
                'WELL_NUM',
                'DAMAGE_STATUS',
                'OPERATIONAL_STATUS',
                'INSTALLATION_DATE',
                'LAST_MAINTENANCE_DATE',
                'WELL_DEPTH',
                'SALINITY',
                'TANK_SHAPE',
                'TANK_MATERIAL',
                'TANK_DIAMETER',
                'TANK_HEIGHT',
                'CHLORINATION_SYSTEM',
                'CHLORINE_TANK_CAPACITY',
                'CHLORINE_TANK_TYPE',
                'CHLORINE_TANK_MATERIAL',
                'CHLORINE_PUMP_MODEL',
                'CHLORINE_PUMP_TYPE',
                'COOLING_TANK_CAPACITY',
                'COOLING_TANK_TYPE',
            ]);
        });
    }
};
