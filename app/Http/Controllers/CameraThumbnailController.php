<?php

namespace App\Http\Controllers;

use App\Management\Rtsp;
use App\Models\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Serves a still-frame JPEG thumbnail for a device's camera.
 *
 * ffmpeg connects to the device's RTSP stream itself and writes a single
 * frame as JPEG to stdout. The result is cached (default 10s) so repeated
 * views and the device list table do not open a new RTSP session per request -
 * the devices only tolerate a handful of concurrent ones.
 */
class CameraThumbnailController extends Controller
{
    public function __construct(private readonly Rtsp $rtsp)
    {
    }

    public function __invoke(Device $device): Response
    {
        $ttl = (int) config('camera.thumbnail.ttl', 10);

        // Cache base64 so the payload is safe across cache drivers (the JPEG is
        // binary). Failures return null, which Cache::remember does not store,
        // so an unreachable camera is retried on the next request.
        $encoded = Cache::remember(
            sprintf('camera-thumbnail:%s', $device->getKey()),
            $ttl,
            function () use ($device): ?string {
                $jpeg = $this->capture($device);

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
     * Grab one frame from the device's RTSP stream. Returns the raw JPEG bytes
     * or null when the camera is unavailable.
     */
    private function capture(Device $device): ?string
    {
        $input = $this->rtsp->ffmpegInput($device);

        if ($input === null) {
            return null;
        }

        $timeout = (float) config('camera.thumbnail.timeout', 10);

        $process = new Process([
            (string) config('camera.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            '-y',
            ...$input,
            '-an',
            '-frames:v', '1',
            '-q:v', '3',
            '-f', 'image2pipe',
            '-vcodec', 'mjpeg',
            'pipe:1',
        ]);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (Throwable $e) {
            // Includes the timeout: a camera that is powered down accepts the
            // TCP connection but never sends a keyframe.
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
