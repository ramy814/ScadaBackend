<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_stops', function (Blueprint $table): void {
            $table->uuid('local_id')->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source', 30)->default('manual');
        });

        Schema::create('trip_tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->uuid('local_id');
            $table->string('event_type', 80)->index();
            $table->timestamp('detected_at')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('details_json')->nullable();
            $table->timestamps();
            $table->unique(['trip_id', 'local_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_tracking_events');
        Schema::table('trip_stops', function (Blueprint $table): void {
            $table->dropUnique(['local_id']);
            $table->dropColumn(['local_id', 'latitude', 'longitude', 'source']);
        });
    }
};
