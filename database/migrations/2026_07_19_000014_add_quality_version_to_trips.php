<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            // Null means a legacy trip. Quality rules are opt-in so existing
            // trips and older app releases keep their original behaviour.
            $table->unsignedSmallInteger('quality_version')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn('quality_version');
        });
    }
};
