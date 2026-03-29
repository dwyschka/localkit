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
        Schema::create('bluetooth_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default(null)->nullable(true);
            $table->string('type');

            $table->string('petkit_id');
            $table->string('mac');
            $table->string('secret');
            $table->string('serial_number');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bluetooth_devices');
    }
};
