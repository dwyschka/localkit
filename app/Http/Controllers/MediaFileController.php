<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MediaPage;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a camera capture by the `fileId` reported in
 * `dev_upload_file_info_v2` (see DevUploadFileInfoV2Controller), with the
 * correct content type. The object on disk is already plaintext -
 * DevUploadFileInfoV2Controller decrypts it in place as soon as the report
 * carrying its IV arrives - so this just streams it straight through.
 */
class MediaFileController extends Controller
{
    public function __invoke(string $fileId): StreamedResponse
    {
        $media = MediaFile::where('file_id', $fileId)->firstOrFail();

        $disk = Storage::disk(MediaPage::DISK);

        abort_unless($disk->exists($media->object_key), 404);

        return $disk->response($media->object_key, null, [
            'Content-Type' => $media->isVideo() ? 'video/mp2t' : 'image/jpeg',
        ]);
    }
}
