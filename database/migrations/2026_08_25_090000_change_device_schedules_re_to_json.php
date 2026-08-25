<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * device_schedules.re was created as a plain string column
     * (2026_08_24_080000_create_device_schedules_table.php) holding the
     * comma-joined day digits ("1,3,5") the device wire format itself uses.
     * localkit's own model/UI/business logic now passes 're' around as an
     * array end-to-end (DeviceSchedule::$casts, Device::scheduleGroups()/
     * syncSchedule(), Time::calculateLatest(), the per-device Filament
     * CheckboxLists) - only Time::toWireRepeatDays() still joins it into a
     * string, right before a schedule reaches the device. That original
     * migration was edited in place to create the column as json directly,
     * which only helps a database that hasn't run it yet - this migration
     * brings one that already has (or was seeded via the companion
     * 2026_08_24_090000 backfill, from back when it still wrote a
     * comma-joined string) up to the same shape: existing rows are rewritten
     * from a comma-joined string to a JSON array, then the column itself is
     * changed to json. Rows already holding a JSON array (a table created
     * fresh off the updated migration) are left alone.
     */
    public function up(): void
    {
        DB::table('device_schedules')->orderBy('id')->cursor()->each(function (object $row) {
            $decoded = json_decode($row->re, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return;
            }

            $days = array_values(array_filter(explode(',', (string) $row->re), fn($day) => $day !== ''));

            DB::table('device_schedules')
                ->where('id', $row->id)
                ->update(['re' => json_encode($days)]);
        });

        Schema::table('device_schedules', function (Blueprint $table) {
            $table->json('re')->change();
        });
    }

    /**
     * Reads every row's array back into a comma-joined string before the
     * column type changes back to string - a json column rejects a
     * non-JSON string value, so the data has to be converted first while
     * it's still readable as JSON.
     */
    public function down(): void
    {
        $rows = DB::table('device_schedules')->orderBy('id')->get(['id', 're']);

        Schema::table('device_schedules', function (Blueprint $table) {
            $table->string('re')->change();
        });

        foreach ($rows as $row) {
            DB::table('device_schedules')
                ->where('id', $row->id)
                ->update(['re' => implode(',', json_decode($row->re, true) ?? [])]);
        }
    }
};
