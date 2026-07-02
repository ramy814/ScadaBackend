<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valve_teams', function (Blueprint $table): void {
            $table->boolean('ASSIGN_ALL_VALVES')->default(false)->after('IS_ACTIVE');
        });
    }

    public function down(): void
    {
        Schema::table('valve_teams', function (Blueprint $table): void {
            $table->dropColumn('ASSIGN_ALL_VALVES');
        });
    }
};
