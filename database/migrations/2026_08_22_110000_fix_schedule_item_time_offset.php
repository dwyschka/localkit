<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The Filament schedule-item form built 'id' and 't' from the same
     * seconds-from-midnight value, but a leftover dehydrateStateUsing step
     * then added 1 to 't' without touching 'id' (e.g. id "n_6060" saved
     * alongside t 6061). Live capture of the real Petkit app's
     * property/set payload shows 'id' and 't' always carry the same
     * number, so this backfills every stored item's 't' back to the
     * seconds encoded in its own 'id'.
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
                        if (!is_array($item) || !isset($item['id'], $item['t'])) {
                            continue;
                        }

                        if (!preg_match('/^n_(\d+)$/', (string) $item['id'], $matches)) {
                            continue;
                        }

                        $idSeconds = (int) $matches[1];

                        if ($idSeconds !== (int) $item['t']) {
                            $item['t'] = $idSeconds;
                            $changed = true;
                        }
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
     * Not reversible: the original (buggy) offset can't be recovered once
     * corrected, since fixed items are indistinguishable from items that
     * never had the bug.
     */
    public function down(): void
    {
    }
};
