<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Before History::create() was switched to updateOrCreate() keyed on
     * messageId, a redelivered MQTT event (retry, reconnect replay) produced
     * multiple history rows sharing the same messageId. Collapses each such
     * group into the earliest row, merging every duplicate's parameters into
     * it first so no data reported only on a later duplicate is lost.
     */
    public function up(): void
    {
        $duplicateMessageIds = DB::table('history')
            ->select('messageId')
            ->whereNotNull('messageId')
            ->groupBy('messageId')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('messageId');

        foreach ($duplicateMessageIds as $messageId) {
            $rows = DB::table('history')
                ->where('messageId', $messageId)
                ->orderBy('id')
                ->get();

            $keep = $rows->first();

            $mergedParameters = [];
            foreach ($rows as $row) {
                $mergedParameters = array_merge($mergedParameters, json_decode($row->parameters ?? '{}', true) ?? []);
            }

            DB::table('history')
                ->where('id', $keep->id)
                ->update(['parameters' => json_encode($mergedParameters)]);

            DB::table('history')
                ->where('messageId', $messageId)
                ->where('id', '!=', $keep->id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: deleted duplicate rows cannot be reconstructed.
    }
};
