<?php

use App\Http\Controllers\CameraThumbnailController;
use App\Http\Controllers\LogDownloadController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\MediaFileController;
use App\Http\Controllers\PetMediaController;
use App\Http\Controllers\Petkit\ObjectStorageController;
use App\Http\Controllers\Petkit\RepositoryController;
use Illuminate\Support\Facades\Route;


if (config('localkit.firmware_proxy')) {
    Route::any('repository/{path}', RepositoryController::class)->where('path', '.*');
}

// Plain (non-Livewire) route so the file streams instead of being read fully
// into memory and base64-encoded through the Livewire AJAX payload.
Route::get('logs/{file}/download', LogDownloadController::class)
    ->middleware('auth')
    ->name('logs.download');

// Same reasoning as logs.download - streams straight from the disk instead
// of through Livewire's memory-buffering file-download support.
Route::get('media/download/{path}', MediaDownloadController::class)
    ->middleware('auth')
    ->where('path', '.*')
    ->name('media.download');

// Decrypted camera capture, looked up by the fileId reported via
// dev_upload_file_info_v2 (see DevUploadFileInfoV2Controller / MediaFile).
Route::get('media/file/{fileId}', MediaFileController::class)
    ->middleware('auth')
    ->name('media.file');

// Cached still-frame thumbnail (ffmpeg) for a device's camera stream.
Route::get('camera/{device}/thumbnail/{stream?}', CameraThumbnailController::class)
    ->name('camera.thumbnail');

// Installer streaming endpoint - protected, CSRF applies on POST
Route::post('installer', [\App\Http\Controllers\InstallerController::class, 'install'])
    ->middleware('auth')
    ->name('installer.run');

// Pet discern reference photo, fetched directly by the device (see
// DevDiscernPicResource) - no auth, extensionless on purpose.
Route::get('pet/media/{id}', PetMediaController::class)
    ->name('pet.media');

/*
 * OCI-compatible object storage emulation used by PetKit cameras.
 * Upload URL (pre-authenticated) is prefixed with `/p/{token}`; the read/domain
 * URL is not. `{object}` captures the full object path after `/o/`.
 */
if (config('localkit.storage.enabled')) {
    Route::match(['put', 'post'], 'oci/p/{token}/n/{namespace}/b/{bucket}/o/{object}', [ObjectStorageController::class, 'put'])
        ->where('object', '.*');

    Route::get('oci/n/{namespace}/b/{bucket}/o/{object}', [ObjectStorageController::class, 'get'])
        ->where('object', '.*');
}
