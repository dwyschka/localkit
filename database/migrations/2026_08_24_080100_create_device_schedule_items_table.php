<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per feed event within a device_schedules day-group -
     * schedule.md §3c's 'it[]' entries. 'item_id' is the wire-format id sent
     * to the device (e.g. 'n_26400', schedule.md §3c/§4g) - kept as-is here
     * rather than deriving it at read time, since it's stored alongside the
     * schedule item today (devices.configuration JSON) and callers may
     * depend on it staying stable across re-reads.
     *
     * 'a' is the single-hopper amount (D3/D4/D4H); 'a1'/'a2' are the
     * dual-hopper amounts (D4SH only, schedule.md §4e) - both nullable since
     * a given device only ever populates one pair, per
     * HandlesFeederSchedule::amountKeys().
     */
    public function up(): void
    {
        Schema::create('device_schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('item_id');
            $table->unsignedInteger('t');
            $table->unsignedInteger('a')->nullable();
            $table->unsignedInteger('a1')->nullable();
            $table->unsignedInteger('a2')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_schedule_items');
    }
};
