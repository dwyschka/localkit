<?php

namespace App\Petkit\Storage;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The device's .ts captures are raw MPEG-TS, which Chrome won't play in a
 * <video> tag. Remuxes (not re-encodes - `-c copy`, so this is fast and
 * lossless) into a fragmented MP4 container carrying the same H.264 bytes,
 * which every browser plays natively.
 */
class VideoRemuxer
{
    public static function toMp4(string $ts): string
    {
        $process = new Process([
            env('FFMPEG_BINARY', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            // Each concatenated segment restarts its own PTS/DTS near zero -
            // raw byte-concatenation preserves those jumps, which players
            // read as a hard reset at every segment boundary (a 16-segment
            // event looks like 16 separate clips instead of one). Regenerate
            // continuous presentation timestamps from packet order instead
            // of trusting the original (discontinuous) ones.
            '-fflags', '+genpts+igndts',
            '-i', 'pipe:0',
            '-c', 'copy',
            // The device's AAC audio is raw ADTS, which MP4 can't mux
            // directly - repackage the bitstream framing (not a re-encode,
            // still lossless) into what MP4 expects. Harmless no-op if a
            // clip has no audio stream at all.
            '-bsf:a', 'aac_adtstoasc',
            '-avoid_negative_ts', 'make_zero',
            '-f', 'mp4',
            '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
            'pipe:1',
        ]);
        $process->setInput($ts);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful() || $process->getOutput() === '') {
            throw new RuntimeException('ffmpeg remux failed: ' . $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * The sibling object key an original .ts key's remuxed MP4 is stored
     * under (see DevUploadFileInfoV2Controller).
     */
    public static function mp4Key(string $tsObjectKey): string
    {
        return preg_replace('/\.ts$/', '.mp4', $tsObjectKey);
    }
}
