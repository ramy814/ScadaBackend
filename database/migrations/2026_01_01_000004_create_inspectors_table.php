<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspectors', function (Blueprint $table) {
            $table->id('OID');
            $table->string('NAME');
            $table->string('PHONE')->unique();
            $table->string('PASSWORD');
            $table->boolean('IS_ACTIVE')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspectors');
    }
};
