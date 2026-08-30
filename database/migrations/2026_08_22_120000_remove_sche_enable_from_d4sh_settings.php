<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * sche_enable isn't a documented property_set key (see schedule.md/d4sh.md,
     * neither lists it) and the D4SH UI toggle for it was removed - drop the
     * stale key from stored configuration so it doesn't linger unused.
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

                if (!isset($configuration['settings']['sche_enable'])) {
                    return;
                }

                unset($configuration['settings']['sche_enable']);

                DB::table('devices')
                    ->where('id', $device->id)
                    ->update(['configuration' => json_encode($configuration)]);
            });
    }

    public function down(): void
    {
    }
};
