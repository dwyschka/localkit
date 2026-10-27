<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drops the :18 seconds offset out of every stored schedule item.
     *
     * Time::toSeconds() briefly added 18 seconds to each item's
     * seconds-of-day (commit 2af4d36) to compensate the device's observed
     * ~6s-early firing. Time::calculateLatest() derives the matching
     * 'latest[]' countdown through toTimeFromSeconds(), which truncates to
     * H:i - so the countdown targeted HH:MM:00 while the item it names said
     * HH:MM:18, and the D4SH fired on the countdown, found no item at that
     * second and reported result:8 / err_code:10 with real_amount 0 (live
     * capture 2026-10-22; the same schedule with whole-minute items fired
     * fine on 2026-08-25).
     *
     * toSeconds() no longer adds it, but rows written while it did keep the
     * offset, so they are rounded down to the whole minute here. 'item_id' is
     * the wire id derived from 't' ('n_<t>'), so it is rewritten alongside -
     * but only when it actually still matches the old value, to leave ids
     * that were edited or came from elsewhere alone.
     *
     * Devices are not pushed to from here. The next save of a feeding plan
     * recomputes the schedule pointer's checksum, which is what
     * propertyChange() watches, and sends the corrected plan.
     */
    public function up(): void
    {
        // Read fully before writing rather than streaming with cursor():
        // these are a handful of rows per device, and updating the same table
        // an unbuffered cursor is still reading from is driver-dependent.
        DB::table('device_schedule_items')
            ->whereRaw('t % 60 <> 0')
            ->orderBy('id')
            ->get()
            ->each(function (object $item) {
                $normalized = $item->t - ($item->t % 60);

                DB::table('device_schedule_items')
                    ->where('id', $item->id)
                    ->update([
                        't' => $normalized,
                        'item_id' => $item->item_id === 'n_' . $item->t
                            ? 'n_' . $normalized
                            : $item->item_id,
                    ]);
            });
    }

    /**
     * Not reversible: the pre-migration value cannot be recovered from the
     * normalized one, and restoring it would reintroduce the mismatch this
     * exists to remove.
     */
    public function down(): void
    {
    }
};
