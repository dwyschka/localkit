<?php

namespace App\Http\Controllers;

use App\Management\Go2RTC;
use App\Models\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Serves a still-frame thumbnail for a device's video stream.
 *
 * Pulled from go2rtc's own `/api/frame.jpeg` endpoint, which transcodes a
 * keyframe to JPEG internally regardless of the source codec (H264/H265/
 * already-JPEG) - unlike `/api/stream.mjpeg`, whose consumer only negotiates
 * JPEG/RAW and silently fails against H264 sources (see
 * AlexxIT/go2rtc pkg/mjpeg/consumer.go). The result is cached (default 30s)
 * so repeated views and the device list table don't re-request per view.
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
     * Grab a single frame via go2rtc's own keyframe endpoint, returning the
     * raw JPEG bytes or null when the stream is unavailable.
     */
    private function capture(Device $device, string $stream): ?string
    {
        $url = $this->go2rtc->frameUrl($device, $stream);

        try {
            $response = Http::timeout((float) config('go2rtc.thumbnail.timeout', 10))->get($url);
        } catch (Throwable $e) {
            Log::warning('Camera thumbnail request failed', [
                'device' => $device->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Camera thumbnail request unsuccessful', [
                'device' => $device->getKey(),
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }
}
