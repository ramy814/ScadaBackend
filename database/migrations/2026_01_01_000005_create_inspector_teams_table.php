<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspector_teams', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('INSPECTOR_OID')->constrained('inspectors', 'OID');
            $table->foreignId('TEAM_OID')->constrained('teams', 'OID');
            $table->timestamp('CREATED_AT')->nullable();

            $table->unique(['INSPECTOR_OID', 'TEAM_OID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspector_teams');
    }
};
