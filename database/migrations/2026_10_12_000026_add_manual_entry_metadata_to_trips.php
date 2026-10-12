<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->string('entry_source', 20)->default('mobile');
            $table->unsignedBigInteger('entered_by_user_id')->nullable();
            $table->string('entered_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->dropColumn(['entry_source', 'entered_by_user_id', 'entered_by_name']);
        });
    }
};
