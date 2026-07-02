<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valve_team_valves', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('TEAM_OID')->constrained('valve_teams', 'OID')->cascadeOnDelete();
            $table->unsignedBigInteger('VALVE_OID')->index();
            $table->timestamp('CREATED_AT')->nullable();

            $table->unique(['TEAM_OID', 'VALVE_OID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valve_team_valves');
    }
};
