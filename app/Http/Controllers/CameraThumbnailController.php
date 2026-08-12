<?php

namespace App\Http\Controllers;

use App\Management\Go2RTC;
use App\Models\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Serves a still-frame thumbnail for a device's video stream.
 *
 * ffmpeg grabs a single JPEG frame from the device's go2rtc MJPEG feed. The
 * result is cached (default 30s) so repeated views and the device list table
 * don't re-run ffmpeg per request.
 */
class CameraThumbnailController extends Controller
{
    public function __construct(private readonly Go2RTC $go2rtc)
    {
    }

    public function __invoke(Device $device, ?string $stream = null): Response
    {
        $stream ??= (string) config('go2rtc.stream');
        $ttl = (int) config('go2rtc.thumbnail.ttl', 30);

        // Cache base64 so the payload is safe across cache drivers (the JPEG is
        // binary). Failures return null, which Cache::remember does not store,
        // so an unreachable camera is retried on the next request.
        $encoded = Cache::remember(
            sprintf('camera-thumbnail:%s:%s', $device->getKey(), $stream),
            $ttl,
            function () use ($device, $stream): ?string {
                $jpeg = $this->capture($device, $stream);

                return $jpeg !== null ? base64_encode($jpeg) : null;
            },
        );

        if (empty($encoded)) {
            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response(base64_decode($encoded), Response::HTTP_OK, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=' . $ttl,
        ]);
    }

    /**
     * Grab a single frame from the stream with ffmpeg, returning the raw JPEG
     * bytes or null when the stream is unavailable.
     */
    private function capture(Device $device, string $stream): ?string
    {
        $source = $this->go2rtc->mjpegUrl($device, $stream);

        $process = new Process([
            (string) config('go2rtc.thumbnail.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            '-y',
            '-i', $source,
            '-frames:v', '1',
            '-q:v', '3',
            '-f', 'image2pipe',
            '-vcodec', 'mjpeg',
            'pipe:1',
        ]);
        $process->setTimeout((float) config('go2rtc.thumbnail.timeout', 10));

        try {
            $process->run();
        } catch (Throwable $e) {
            Log::warning('Camera thumbnail ffmpeg error', [
                'device' => $device->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('Camera thumbnail ffmpeg failed', [
                'device' => $device->getKey(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return null;
        }

        $output = $process->getOutput();

        return $output !== '' ? $output : null;
    }
}
