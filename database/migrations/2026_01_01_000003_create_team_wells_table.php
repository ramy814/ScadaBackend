<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_wells', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('TEAM_OID')->constrained('teams', 'OID')->cascadeOnDelete();
            $table->foreignId('WELL_OID')->constrained('wells', 'OID')->cascadeOnDelete();
            $table->timestamp('CREATED_AT')->nullable();

            $table->unique(['TEAM_OID', 'WELL_OID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_wells');
    }
};
