<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('valve_logs', function (Blueprint $table): void {
            $table->string('CLIENT_LOCAL_ID', 64)->nullable();
            $table->unique(['USER_OID', 'CLIENT_LOCAL_ID'], 'VLOG_CLIENT_LOCAL_UK');
        });
    }

    public function down(): void
    {
        Schema::table('valve_logs', function (Blueprint $table): void {
            $table->dropUnique('VLOG_CLIENT_LOCAL_UK');
            $table->dropColumn('CLIENT_LOCAL_ID');
        });
    }
};
