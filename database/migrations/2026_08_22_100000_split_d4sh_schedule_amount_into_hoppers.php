<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * D4SH (YumshareDual) schedule items used to carry a single 'a' amount,
     * but the device is dual-hopper and its real schedule format splits that
     * into 'a1'/'a2' - one amount per hopper. Backfills every stored D4SH
     * schedule item's 'a' into 'a1' (hopper 1), leaving 'a2' at 0 until the
     * user configures hopper 2 per item.
     */
    public function up(): void
    {
        DB::table('devices')
            ->where('device_type', 'd4sh')
            ->whereNotNull('configuration')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $device) {
                $configuration = json_decode($device->configuration, true);

                if (!isset($configuration['schedule']) || !is_array($configuration['schedule'])) {
                    return;
                }

                foreach ($configuration['schedule'] as &$daySchedule) {
                    if (!isset($daySchedule['it']) || !is_array($daySchedule['it'])) {
                        continue;
                    }

                    foreach ($daySchedule['it'] as &$item) {
                        if (!is_array($item) || !array_key_exists('a', $item)) {
                            continue;
                        }

                        $item['a1'] = (int) $item['a'];
                        $item['a2'] = 0;
                        unset($item['a']);
                    }
                    unset($item);
                }
                unset($daySchedule);

                DB::table('devices')
                    ->where('id', $device->id)
                    ->update(['configuration' => json_encode($configuration)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('devices')
            ->where('device_type', 'd4sh')
            ->whereNotNull('configuration')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $device) {
                $configuration = json_decode($device->configuration, true);

                if (!isset($configuration['schedule']) || !is_array($configuration['schedule'])) {
                    return;
                }

                foreach ($configuration['schedule'] as &$daySchedule) {
                    if (!isset($daySchedule['it']) || !is_array($daySchedule['it'])) {
                        continue;
                    }

                    foreach ($daySchedule['it'] as &$item) {
                        if (!is_array($item) || !array_key_exists('a1', $item)) {
                            continue;
                        }

                        $item['a'] = (int) $item['a1'];
                        unset($item['a1'], $item['a2']);
                    }
                    unset($item);
                }
                unset($daySchedule);

                DB::table('devices')
                    ->where('id', $device->id)
                    ->update(['configuration' => json_encode($configuration)]);
            });
    }
};
