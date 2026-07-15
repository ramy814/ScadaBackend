<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('wells')
            ->where('PUMP_TYPE', 'centrifugal')
            ->update(['PUMP_TYPE' => 'vertical']);

        DB::table('wells')
            ->where('TANK_MATERIAL', 'بلاستيك مقوى')
            ->update(['TANK_MATERIAL' => 'HDPE']);

        DB::table('wells')
            ->where('CHLORINATION_SYSTEM', 'لا يوجد نظام كلورة')
            ->update(['CHLORINATION_SYSTEM' => 'لا يوجد']);

        DB::table('wells')
            ->whereIn('CHLORINATION_SYSTEM', ['كلور غاز', 'كلور سائل', 'كلور صلب'])
            ->update(['CHLORINATION_SYSTEM' => 'يوجد']);
    }

    public function down(): void
    {
        // Historical values cannot be reconstructed safely after normalization.
    }
};
