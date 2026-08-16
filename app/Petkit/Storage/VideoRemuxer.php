<?php

namespace App\Petkit\Storage;

use Illuminate\Support\Str;
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
     * Joins a new MPEG-TS segment onto an already-combined one using
     * ffmpeg's concat demuxer, which - unlike raw byte concatenation - shifts
     * the new segment's timestamps to continue where the previous one left
     * off. Each ~4s segment restarts its own PTS/DTS near zero; byte-pasting
     * them preserves those jumps, which players read as a hard reset at
     * every boundary (a 16-segment event plays as 16 separate resetting
     * clips instead of one). `-c copy` throughout, so this is lossless and
     * doesn't re-encode anything - just repackages/retimes.
     *
     * Needs real files (not pipes) - the concat demuxer reads a list of
     * file paths, it can't take multiple piped inputs.
     */
    public static function concatTs(string $existingTs, string $newSegmentTs): string
    {
        $dir = sys_get_temp_dir() . '/petkit-concat-' . Str::random(16);
        mkdir($dir);

        try {
            file_put_contents($dir . '/a.ts', $existingTs);
            file_put_contents($dir . '/b.ts', $newSegmentTs);
            file_put_contents($dir . '/list.txt', "file 'a.ts'\nfile 'b.ts'\n");

            $process = new Process([
                env('FFMPEG_BINARY', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error',
                '-f', 'concat',
                '-safe', '0',
                '-i', $dir . '/list.txt',
                '-c', 'copy',
                '-avoid_negative_ts', 'make_zero',
                '-f', 'mpegts',
                'pipe:1',
            ]);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful() || $process->getOutput() === '') {
                throw new RuntimeException('ffmpeg concat failed: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        } finally {
            @unlink($dir . '/a.ts');
            @unlink($dir . '/b.ts');
            @unlink($dir . '/list.txt');
            @rmdir($dir);
        }
    }

    /**
     * The sibling object key an original .ts key's remuxed MP4 is stored
     * under (see DevUploadFileInfoV2Controller).
     */
    public static function mp4Key(string $tsObjectKey): string
    {
        return preg_replace('/\.ts$/', '.mp4', $tsObjectKey);
    }

    /**
     * The reverse of mp4Key() - the combined .ts an object key's MP4 was
     * remuxed from (see DevUploadFileInfoV2Controller::appendCloudStorageSegment,
     * which keeps the accumulator .ts around alongside the remuxed MP4).
     * A no-op if the key is already a .ts.
     */
    public static function tsKey(string $objectKey): string
    {
        return preg_replace('/\.mp4$/', '.ts', $objectKey);
    }
}
