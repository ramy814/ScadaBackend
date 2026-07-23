<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('well_generators', function (Blueprint $table): void {
            $table->decimal('FUEL_CONSUMPTION_LPH', 10, 2)->nullable();
            $table->decimal('CONSUMPTION_TOLERANCE_LPH', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('well_generators', function (Blueprint $table): void {
            $table->dropColumn([
                'FUEL_CONSUMPTION_LPH',
                'CONSUMPTION_TOLERANCE_LPH',
            ]);
        });
    }
};
