<?php

namespace App\Management;

use Throwable;
use App\Models\Device;
use Illuminate\Support\Facades\Http;

/**
 * Every HasCamera device runs its own go2rtc server on its local IP. This client
 * builds the URLs to reach that per-device server - there is no central go2rtc.
 */
class Go2RTC
{
    public function streamUrl(Device $device, ?string $stream = null): string
    {
        return $this->url($device, '/stream.html', $stream ?? config('go2rtc.stream'));
    }

    public function frameUrl(Device $device, ?string $stream = null): string
    {
        return $this->url($device, '/api/frame.jpeg', $stream ?? config('go2rtc.stream'));
    }

    /**
     * A single keyframe wrapped in a minimal fMP4 fragment (video/mp4) - no
     * transcoding involved, go2rtc just repackages whatever codec the stream
     * already has. Used for thumbnails where /api/frame.jpeg's internal
     * H264->JPEG transcode isn't reliable.
     */
    public function frameMp4Url(Device $device, ?string $stream = null): string
    {
        return $this->url($device, '/api/frame.mp4', $stream ?? config('go2rtc.stream'));
    }

    /**
     * Root-relative URL of the cached still-frame thumbnail served by localkit.
     * Relative so it resolves against whatever host serves the panel.
     */
    public function thumbnailUrl(Device $device, ?string $stream = null): string
    {
        return route('camera.thumbnail', [
            'device' => $device->getKey(),
            'stream' => $stream ?? config('go2rtc.stream'),
        ], absolute: false);
    }

    /**
     * Query the device's go2rtc for every configured stream name.
     *
     * @return array<int, string>
     */
    public function streams(Device $device): array
    {
        $ip = $this->deviceIp($device);
        if ($ip === null) {
            return [];
        }

        try {
            $response = Http::timeout(3)->get(sprintf('http://%s:%d/api/streams', $ip, config('go2rtc.port')));

            if (!$response->successful()) {
                return [];
            }

            return array_keys($response->json() ?? []);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Map every stream on the device to its cached thumbnail URL. Camera
     * previews show this still frame instead of a live stream.
     *
     * @return array<string, string>
     */
    public function thumbnailUrls(Device $device): array
    {
        $urls = [];
        foreach ($this->streams($device) as $stream) {
            $urls[$stream] = $this->thumbnailUrl($device, $stream);
        }

        return $urls;
    }

    private function url(Device $device, string $path, string $stream): string
    {
        return sprintf(
            'http://%s:%d%s?src=%s',
            $this->deviceIp($device),
            config('go2rtc.port'),
            $path,
            $stream
        );
    }

    private function deviceIp(Device $device): ?string
    {
        return $device->configuration['states']['ipAddress'] ?? null;
    }
}
