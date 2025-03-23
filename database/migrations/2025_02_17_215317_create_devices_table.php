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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(true);
            $table->string('firmware')->nullable(false);
            $table->string('mac')->nullable(false);
            $table->string('timezone')->nullable(false);
            $table->string('locale')->nullable(false);
            $table->unsignedInteger('petkit_id')->nullable(false);
            $table->string('serial_number')->nullable(false);
            $table->string('bt_mac')->nullable(false);
            $table->string('ap_mac')->nullable(false);
            $table->string('chip_id')->nullable(false);
            $table->string('device_type')->nullable(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
