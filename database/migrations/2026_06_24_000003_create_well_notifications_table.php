<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('well_notifications', function (Blueprint $table) {
            $table->id('OID');
            $table->unsignedBigInteger('WELL_OID');
            $table->unsignedBigInteger('NOTIFICATION_TYPE_OID');
            $table->unsignedBigInteger('INSPECTOR_OID');
            $table->text('MESSAGE')->nullable();
            $table->boolean('IS_READ')->default(false);
            $table->timestamp('READ_AT')->nullable();
            $table->timestamps();

            $table->foreign('WELL_OID')->references('OID')->on('wells')->onDelete('cascade');
            $table->foreign('NOTIFICATION_TYPE_OID')->references('OID')->on('notification_types');
            $table->foreign('INSPECTOR_OID')->references('oid')->on('inspectors')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('well_notifications');
    }
};
