<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['well_readings', 'diesel_inventories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('DELETED_AT')->nullable();
                $blueprint->unsignedBigInteger('DELETED_BY_USER_ID')->nullable();
                $blueprint->string('DELETED_BY_NAME', 255)->nullable();
                $blueprint->string('DELETE_REASON', 1000)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['well_readings', 'diesel_inventories'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'DELETED_AT',
                    'DELETED_BY_USER_ID',
                    'DELETED_BY_NAME',
                    'DELETE_REASON',
                ]);
            });
        }
    }
};
