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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();

            $table->string('file_id')->unique();
            $table->foreignId('device_id')->nullable()->index();
            $table->string('event_id')->nullable();
            $table->string('module_type')->nullable();
            $table->string('file_type')->nullable();

            // Object storage key the encrypted upload was persisted under
            // (see DeviceObjectStorage / ObjectStorageController).
            $table->string('object_key');

            // aesIv field reported alongside the file ("0x" + 32 hex chars).
            // The AES key itself is not stored per-file - it's the single
            // key handed out via dev_oss_sts_info_new_v2 (config('localkit.storage.aes_key')).
            $table->string('aes_iv')->nullable();

            // Guards against decrypting the object twice (e.g. the device
            // retrying its dev_upload_file_info_v2 report) - AES-CBC
            // decrypting already-plaintext bytes a second time corrupts them.
            $table->boolean('decrypted')->default(false);

            $table->unsignedInteger('pet_score')->nullable();
            $table->unsignedInteger('eat_score')->nullable();
            $table->unsignedInteger('move_score')->nullable();
            $table->unsignedInteger('feed_score')->nullable();

            $table->unsignedInteger('start_time')->nullable();
            $table->unsignedInteger('end_time')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
