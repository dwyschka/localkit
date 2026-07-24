<?php

namespace App\Management;

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
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Map every stream on the device to its embeddable player URL.
     *
     * @return array<string, string>
     */
    public function streamUrls(Device $device): array
    {
        $urls = [];
        foreach ($this->streams($device) as $stream) {
            $urls[$stream] = $this->streamUrl($device, $stream);
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
