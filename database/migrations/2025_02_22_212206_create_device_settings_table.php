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
        Schema::create('device_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->integer('sand_type')->default(1);
            $table->boolean('manual_lock')->default(0);
            $table->boolean('light_mode')->default(1);
            $table->json('light_range')->nullable(true);
            $table->boolean('auto_work')->default(1);
            $table->integer('fixed_time_clear')->default(0);
            $table->boolean('downpos')->default(1);
            $table->boolean('deep_refresh')->default(0);
            $table->integer('auto_interval_min')->default(0);
            $table->integer('still_time')->default(60);
            $table->integer('unit')->default(0);
            $table->string('language')->default('de_DE');
            $table->boolean('avoid_repeat')->default(1);
            $table->boolean('kitten')->default(0);
            $table->integer('stop_time')->default(600);
            $table->json('sand_full_weight')->nullable(true);
            $table->boolean('disturb_mode')->default(0);
            $table->json('disturb_range')->nullable(true);
            $table->json('sand_set_use_config')->nullable(true);
            $table->boolean('has_k3')->default(0);
            $table->boolean('has_multiconfig')->default(0);
            $table->boolean('deep_clean')->default(0);
            $table->integer('lightest')->default(1680);
            $table->boolean('remove_sand')->default(1);
            $table->boolean('bury')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_config');
    }
};
