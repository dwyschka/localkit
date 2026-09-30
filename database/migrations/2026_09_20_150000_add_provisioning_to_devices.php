<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `provisioning` marks a device that was set up over BLE from this panel
     * and should have its built-in telnetd started. While it is set, the
     * heartbeat hands the device a run_cmd that brings telnetd up (idempotently,
     * so it survives a reboot without stacking processes). Cleared from the
     * device's own page once telnet is no longer wanted.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('provisioning')->default(false)->after('debug_mode');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('provisioning');
        });
    }
};
