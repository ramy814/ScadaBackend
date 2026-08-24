<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_operational_states', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('scope', 50);
            $table->boolean('is_suspended')->default(false);
            $table->string('reason', 1000)->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamp('changed_at')->nullable();

            $table->unique('scope', 'sos_scope_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_operational_states');
    }
};
