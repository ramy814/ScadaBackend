<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('both'); // origin|destination|both
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $names = [
            'مجمّع البلدية الرئيس',
            'المبنى الأثري',
            'مبنى السرايا',
            'مبنى أبو أيوب الأنصاري',
            'مبنى الكهرباء والمخازن',
            'كراج البلدية والطوارئ',
            'مقر التفتيش والمتابعة',
            'محكمة صلح بلدية غزة',
            'المسلخ البلدي',
            'المراكز المجتمعية الفرعية',
            'مكتب الصحة والبيئة – الشجاعية',
        ];

        $now = now();
        $rows = [];
        foreach ($names as $idx => $name) {
            $rows[] = [
                'name' => $name,
                'category' => 'both',
                'sort_order' => $idx + 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('mission_locations')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_locations');
    }
};

