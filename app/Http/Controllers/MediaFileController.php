<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MediaPage;
use App\Models\MediaFile;
use App\Petkit\Storage\VideoRemuxer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Serves a camera capture by the `fileId` reported in
 * `dev_upload_file_info_v2` (see DevUploadFileInfoV2Controller). The object
 * on disk is already plaintext - DevUploadFileInfoV2Controller decrypts it
 * in place as soon as the report carrying its IV arrives, and remuxes video
 * to MP4 at the same time (see VideoRemuxer::mp4Key()).
 */
class MediaFileController extends Controller
{
    public function __invoke(string $fileId): StreamedResponse|Response
    {
        $media = MediaFile::where('file_id', $fileId)->firstOrFail();

        $disk = Storage::disk(MediaPage::DISK);

        if (! $media->isVideo()) {
            abort_unless($disk->exists($media->object_key), 404);

            return $disk->response($media->object_key, null, [
                'Content-Type' => 'image/jpeg',
            ]);
        }

        $mp4Key = VideoRemuxer::mp4Key($media->object_key);

        if ($disk->exists($mp4Key)) {
            return $disk->response($mp4Key, null, ['Content-Type' => 'video/mp4']);
        }

        // Fallback for captures decrypted before the upload-time remux
        // existed - convert on the fly instead of 404ing.
        abort_unless($disk->exists($media->object_key), 404);

        try {
            $mp4 = VideoRemuxer::toMp4($disk->get($media->object_key));
        } catch (Throwable $e) {
            Log::warning('TS to MP4 remux failed', ['object' => $media->object_key, 'error' => $e->getMessage()]);
            abort(502, 'Video conversion failed');
        }

        return response($mp4, 200, ['Content-Type' => 'video/mp4']);
    }
}
