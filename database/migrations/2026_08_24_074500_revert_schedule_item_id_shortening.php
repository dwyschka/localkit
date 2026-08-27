<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reverts 2026_08_23_140000_shorten_schedule_item_ids_for_null_terminator.
     * That migration dropped the underscore from schedule item ids
     * ('n_%d' -> 'n%d') on the theory that the on-device buffer-overrun risk
     * (garbage bytes past an unterminated 7-byte id, seen in a device debug
     * log) needed a byte of headroom to avoid breaking the device.
     *
     * A live side-by-side test on 2026-08-24 disproved that: the real
     * Petkit app sends the full unterminated 'n_%d' form and the device
     * fires on schedule; localkit's shortened 'n%d' form, sent for the same
     * schedule at the same time, silently failed to fire at all. Whatever
     * the debug-log corruption was, it isn't what breaks scheduling - the
     * shortening was actively harmful, not just unnecessary.
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

                        if (!preg_match('/^n(\d+)$/', (string) $item['id'], $matches)) {
                            continue;
                        }

                        $item['id'] = 'n_' . $matches[1];
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
     * Not reversible, matching the migration this undoes.
     */
    public function down(): void
    {
    }
};
