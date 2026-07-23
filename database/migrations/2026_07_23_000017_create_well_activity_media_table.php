<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('well_activity_media', function (Blueprint $table) {
            $table->id('OID');
            $table->string('ACTIVITY_TYPE', 30);
            $table->unsignedBigInteger('ACTIVITY_OID');
            $table->string('CLIENT_LOCAL_ID', 64);
            $table->string('FILE_PATH', 500);
            $table->string('ORIGINAL_NAME');
            $table->string('MIME_TYPE', 100);
            $table->unsignedBigInteger('FILE_SIZE');
            $table->timestamps();

            $table->index(
                ['ACTIVITY_TYPE', 'ACTIVITY_OID'],
                'WAM_ACTIVITY_IDX',
            );
            $table->unique('CLIENT_LOCAL_ID', 'WAM_CLIENT_LOCAL_UK');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_activity_media');
    }
};
