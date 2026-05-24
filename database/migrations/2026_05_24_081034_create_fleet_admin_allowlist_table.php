<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('oracle')->create('FLEET_ADMIN_ALLOWLIST', function (Blueprint $table) {
            $table->id();
            $table->string('emp_no', 50);
            $table->string('label', 255)->nullable();
            $table->string('added_by', 255)->nullable();
            $table->timestamps();

            $table->unique('emp_no', 'faa_emp_no_uq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('oracle')->dropIfExists('FLEET_ADMIN_ALLOWLIST');
    }
};
