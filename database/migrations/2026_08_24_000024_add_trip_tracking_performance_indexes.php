<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            $table->index('started_at', 'tr_started_ix');
            $table->index(['status', 'started_at'], 'tr_status_started_ix');
            $table->index(['ended_at', 'points_received'], 'tr_visibility_ix');
        });

        Schema::table('trip_points', function (Blueprint $table): void {
            $table->index(['trip_id', 'recorded_at'], 'tp_trip_recorded_ix');
        });

        Schema::table('trip_tracking_events', function (Blueprint $table): void {
            $table->index(['trip_id', 'event_type'], 'tte_trip_type_ix');
            $table->index(['trip_id', 'detected_at'], 'tte_trip_detected_ix');
        });
    }

    public function down(): void
    {
        Schema::table('trip_tracking_events', function (Blueprint $table): void {
            $table->dropIndex('tte_trip_detected_ix');
            $table->dropIndex('tte_trip_type_ix');
        });

        Schema::table('trip_points', function (Blueprint $table): void {
            $table->dropIndex('tp_trip_recorded_ix');
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropIndex('tr_visibility_ix');
            $table->dropIndex('tr_status_started_ix');
            $table->dropIndex('tr_started_ix');
        });
    }
};
