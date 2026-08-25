<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills device_schedules/device_schedule_items from every device's
     * existing configuration['schedule'] JSON array, then rewrites that key
     * down to the pointer shape (['key' => 'default', 'checksum' => ...])
     * Device::scheduleGroups()/HandlesFeederSchedule now expect. Without
     * this, every device saved before this pass would silently lose its
     * schedule the moment anything reads configuration['schedule']['key']
     * off the old raw array (no 'key' index there -> falls back to
     * 'default' -> an empty device_schedules table).
     *
     * Skips any device whose 'schedule' is already a pointer (has a 'key'
     * key) - i.e. a device created/saved after this change went live, or a
     * safe re-run.
     */
    public function up(): void
    {
        DB::table('devices')
            ->whereNotNull('configuration')
            ->orderBy('id')
            ->cursor()
            ->each(function (object $device) {
                $configuration = json_decode($device->configuration, true);

                if (!isset($configuration['schedule']) || !is_array($configuration['schedule'])) {
                    return;
                }

                $schedule = $configuration['schedule'];

                if (array_key_exists('key', $schedule)) {
                    return;
                }

                $groups = array_values($schedule);
                $key = 'default';
                $now = now();

                foreach ($groups as $group) {
                    if (!is_array($group) || !isset($group['re'])) {
                        continue;
                    }

                    $scheduleId = DB::table('device_schedules')->insertGetId([
                        'device_id' => $device->id,
                        'key' => $key,
                        're' => (string) $group['re'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach (($group['it'] ?? []) as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        DB::table('device_schedule_items')->insert([
                            'device_schedule_id' => $scheduleId,
                            'item_id' => (string) ($item['id'] ?? ''),
                            't' => (int) ($item['t'] ?? 0),
                            'a' => array_key_exists('a', $item) ? (int) $item['a'] : null,
                            'a1' => array_key_exists('a1', $item) ? (int) $item['a1'] : null,
                            'a2' => array_key_exists('a2', $item) ? (int) $item['a2'] : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                $configuration['schedule'] = [
                    'key' => $key,
                    'checksum' => md5(json_encode($groups)),
                ];

                DB::table('devices')
                    ->where('id', $device->id)
                    ->update(['configuration' => json_encode($configuration)]);
            });
    }

    /**
     * Not reversible, matching the sibling id-format migrations this
     * session - reconstructing the exact pre-migration JSON shape isn't
     * worth carrying a down() for.
     */
    public function down(): void
    {
    }
};
