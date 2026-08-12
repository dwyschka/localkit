<?php

use App\Http\Controllers\CameraThumbnailController;
use App\Http\Controllers\Petkit\ObjectStorageController;
use App\Http\Controllers\Petkit\RepositoryController;
use Illuminate\Support\Facades\Route;


if (config('localkit.firmware_proxy')) {
    Route::any('repository/{path}', RepositoryController::class)->where('path', '.*');
}

// Cached still-frame thumbnail (ffmpeg) for a device's camera stream.
Route::get('camera/{device}/thumbnail/{stream?}', CameraThumbnailController::class)
    ->name('camera.thumbnail');

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
