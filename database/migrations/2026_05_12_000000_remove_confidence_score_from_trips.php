<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trips', 'confidence_score')) {
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('confidence_score');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('trips', 'confidence_score')) {
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedTinyInteger('confidence_score')->nullable();
        });
    }
};
