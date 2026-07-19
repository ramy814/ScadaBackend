<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_points', function (Blueprint $table): void {
            $table->timestamp('recorded_at')->nullable();
            $table->boolean('client_is_valid')->default(true);
            $table->string('quality_reason', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trip_points', function (Blueprint $table): void {
            $table->dropColumn([
                'recorded_at',
                'client_is_valid',
                'quality_reason',
            ]);
        });
    }
};
