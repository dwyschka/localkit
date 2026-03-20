<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bluetooth_devices', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Device::class, 'link_with')
                ->nullable()
                ->after('mac');

            $table->integer('interval')->default(240);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bluetooth_devices', function (Blueprint $table) {
            $table->dropColumn('link_with');
            $table->dropColumn('interval');
        });
    }
};
