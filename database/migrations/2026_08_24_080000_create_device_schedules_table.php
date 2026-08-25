<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normalized storage for the feeding schedule currently held as the
     * 'schedule' key inside devices.configuration (JSON). This table holds
     * one row per day-group (schedule.md §3c's 're'+'it' shape, minus the
     * items themselves - see device_schedule_items).
     *
     * 'key' exists so a device can eventually carry more than one named
     * schedule (e.g. a "vacation" schedule alongside the default one) without
     * a further schema change - not used for anything yet, every row today
     * is expected to use the same default key.
     *
     * This migration only creates the table; devices.configuration['schedule']
     * (JSON) remains the source of truth read/written by Time.php and the
     * Filament forms until a follow-up migrates the app over to this table.
     */
    public function up(): void
    {
        Schema::create('device_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('key')->default('default');
            $table->json('re');
            $table->timestamps();

            $table->index(['device_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_schedules');
    }
};
