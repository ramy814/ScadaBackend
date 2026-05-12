<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $seed = [
            'نقل موظفين',
            'نقل معدات',
            'مهمة ميدانية',
            'طوارئ',
            'صيانة',
        ];
        $rows = [];
        $now = now();
        foreach ($seed as $i => $name) {
            $rows[] = [
                'name' => $name,
                'sort_order' => $i + 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('mission_types')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_types');
    }
};

