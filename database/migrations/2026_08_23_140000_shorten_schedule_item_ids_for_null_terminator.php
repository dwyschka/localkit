<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * schedule.md's on-device struct for a schedule item is `id[7]` (7
     * bytes, no separate null-terminator byte). The old 'n_%d' id format
     * (2-char prefix + up to 5-digit seconds-of-day) hits exactly 7 bytes
     * whenever the time is >= 10000s - i.e. most of the day - leaving no
     * room for termination. A live D4SH capture (2026-08-23) showed the
     * device's own debug print of exactly such an id with garbage bytes
     * appended right after the 7th character, consistent with it reading
     * past an unterminated buffer.
     *
     * The app-side fix (this same session) drops the underscore for all
     * *new* ids going forward ('n%d', max 6 bytes). This backfills every
     * already-stored schedule item's 'id' to the same shorter form, so
     * existing schedules don't need a manual re-save to stop hitting the
     * bug the next time they're pushed to the device.
     */
    private const AFFECTED_DEVICE_TYPES = ['d3', 'd4', 'd4h', 'd4sh'];

    public function up(): void
    {
        DB::table('devices')
            ->whereIn('device_type', self::AFFECTED_DEVICE_TYPES)
            ->whereNotNull('configuration')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $device) {
                $configuration = json_decode($device->configuration, true);

                if (!isset($configuration['schedule']) || !is_array($configuration['schedule'])) {
                    return;
                }

                $changed = false;

                foreach ($configuration['schedule'] as &$daySchedule) {
                    if (!isset($daySchedule['it']) || !is_array($daySchedule['it'])) {
                        continue;
                    }

                    foreach ($daySchedule['it'] as &$item) {
                        if (!is_array($item) || !isset($item['id'])) {
                            continue;
                        }

                        if (!preg_match('/^n_(\d+)$/', (string) $item['id'], $matches)) {
                            continue;
                        }

                        $item['id'] = 'n' . $matches[1];
                        $changed = true;
                    }
                    unset($item);
                }
                unset($daySchedule);

                if ($changed) {
                    DB::table('devices')
                        ->where('id', $device->id)
                        ->update(['configuration' => json_encode($configuration)]);
                }
            });
    }

    /**
     * Not reversible: re-inserting the underscore is recoverable in theory
     * (unlike the time-offset fix this mirrors), but there's no value in
     * restoring a format that's a known on-device buffer overrun risk.
     */
    public function down(): void
    {
    }
};
