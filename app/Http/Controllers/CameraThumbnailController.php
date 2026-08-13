<?php

namespace App\Http\Controllers;

use App\Management\Go2RTC;
use App\Models\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Serves a still-frame JPEG thumbnail for a device's video stream.
 *
 * We fetch go2rtc's `/api/frame.mp4` endpoint ourselves (Laravel's HTTP
 * client, not ffmpeg's own network I/O) and pipe the bytes into ffmpeg via
 * stdin to convert to JPEG. frame.mp4 is used as the source (rather than
 * feeding ffmpeg the raw device stream, or using go2rtc's own
 * `/api/frame.jpeg`) because it reliably repackages whatever codec the
 * stream has (H264/H265) into a minimal fMP4 fragment with no transcoding
 * on go2rtc's side - see AlexxIT/go2rtc internal/mp4/mp4.go. The result is
 * cached (default 10s) so repeated views and the device list table don't
 * re-fetch/re-run ffmpeg per request.
 */
class CameraThumbnailController extends Controller
{
    public function __construct(private readonly Go2RTC $go2rtc)
    {
    }

    public function __invoke(Device $device, ?string $stream = null): Response
    {
        $stream ??= (string) config('go2rtc.stream');
        $ttl = (int) config('go2rtc.thumbnail.ttl', 10);

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
     * Fetch go2rtc's frame.mp4 ourselves, then convert it to JPEG with
     * ffmpeg reading from stdin. Returns the raw JPEG bytes or null when
     * the stream is unavailable.
     */
    private function capture(Device $device, string $stream): ?string
    {
        $timeout = (float) config('go2rtc.thumbnail.timeout', 10);
        $url = $this->go2rtc->frameMp4Url($device, $stream);

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (Throwable $e) {
            Log::warning('Camera thumbnail frame.mp4 request failed', [
                'device' => $device->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful() || $response->body() === '') {
            Log::warning('Camera thumbnail frame.mp4 request unsuccessful', [
                'device' => $device->getKey(),
                'status' => $response->status(),
            ]);

            return null;
        }

        $process = new Process([
            (string) config('go2rtc.thumbnail.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            '-y',
            '-i', 'pipe:0',
            '-frames:v', '1',
            '-q:v', '3',
            '-f', 'image2pipe',
            '-vcodec', 'mjpeg',
            'pipe:1',
        ]);
        $process->setInput($response->body());
        $process->setTimeout($timeout);

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
