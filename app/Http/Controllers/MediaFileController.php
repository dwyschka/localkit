<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MediaPage;
use App\Models\MediaFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Serves a camera capture by the `fileId` reported in
 * `dev_upload_file_info_v2` (see DevUploadFileInfoV2Controller). The object
 * on disk is already plaintext - DevUploadFileInfoV2Controller decrypts it
 * in place as soon as the report carrying its IV arrives.
 */
class MediaFileController extends Controller
{
    public function __invoke(string $fileId): StreamedResponse|Response
    {
        $media = MediaFile::where('file_id', $fileId)->firstOrFail();

        $disk = Storage::disk(MediaPage::DISK);

        abort_unless($disk->exists($media->object_key), 404);

        if (! $media->isVideo()) {
            return $disk->response($media->object_key, null, [
                'Content-Type' => 'image/jpeg',
            ]);
        }

        return $this->remuxToMp4($disk->get($media->object_key));
    }

    /**
     * The device's .ts segments are raw MPEG-TS, which Chrome won't play in
     * a <video> tag. Remux (not re-encode - `-c copy`, so this is fast and
     * lossless) into a fragmented MP4 container carrying the same H.264
     * bytes, which every browser plays natively.
     */
    private function remuxToMp4(string $ts): Response
    {
        $process = new Process([
            env('FFMPEG_BINARY', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            '-i', 'pipe:0',
            '-c', 'copy',
            '-f', 'mp4',
            '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
            'pipe:1',
        ]);
        $process->setInput($ts);
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (Throwable $e) {
            Log::warning('TS to MP4 remux failed', ['error' => $e->getMessage()]);
            abort(502, 'Video conversion failed');
        }

        if (! $process->isSuccessful() || $process->getOutput() === '') {
            Log::warning('TS to MP4 remux failed', ['stderr' => $process->getErrorOutput()]);
            abort(502, 'Video conversion failed');
        }

        return response($process->getOutput(), 200, ['Content-Type' => 'video/mp4']);
    }
}
