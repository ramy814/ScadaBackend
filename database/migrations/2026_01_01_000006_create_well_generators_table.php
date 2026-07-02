<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('well_generators', function (Blueprint $table) {
            $table->id('OID');
            $table->foreignId('WELL_OID')->constrained('wells', 'OID')->cascadeOnDelete();
            $table->integer('GENERATOR_OID')->comment('References G_STORE.GENERATOR');
            $table->timestamp('CREATED_AT')->nullable();

            $table->unique(['WELL_OID', 'GENERATOR_OID']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_generators');
    }
};
