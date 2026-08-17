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
        Schema::table('media_files', function (Blueprint $table) {
            // Per-segment metadata for combined CLOUD_STORAGE event videos
            // (see DevUploadFileInfoV2Controller::appendCloudStorageSegment) -
            // the raw segments themselves get merged and deleted, so this is
            // the only record of what went into the combined file.
            $table->json('segments')->nullable()->after('object_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_files', function (Blueprint $table) {
            $table->dropColumn('segments');
        });
    }
};
