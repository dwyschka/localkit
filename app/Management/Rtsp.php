<?php

namespace App\Management;

use App\Models\Device;

/**
 * Every camera equipped device serves a single RTSP stream on its local IP.
 * There is no stream index to query and no second stream - the URL is always
 * rtsp://<ip>:8554/stream0 - so this class just derives URLs from the device's
 * IP address: the RTSP URL itself (for ffmpeg) and the localkit routes that
 * wrap it for the browser, which cannot speak RTSP.
 */
class Rtsp
{
    /**
     * The device's RTSP URL, or null when we have not learned its IP yet.
     */
    public function url(Device $device): ?string
    {
        $ip = $this->deviceIp($device);

        return $ip !== null ? $this->urlForIp($ip) : null;
    }

    public function urlForIp(string $ip): string
    {
        return sprintf(
            'rtsp://%s:%d/%s',
            $ip,
            config('camera.rtsp.port'),
            ltrim((string) config('camera.rtsp.path'), '/')
        );
    }

    public function available(Device $device): bool
    {
        return $this->deviceIp($device) !== null;
    }

    /**
     * Root-relative URL of the MPEG-TS live stream served by localkit, for
     * mpegts.js to play. Relative so it resolves against whatever host serves
     * the panel.
     */
    public function liveUrl(Device $device): string
    {
        return route('camera.live', ['device' => $device->getKey()], absolute: false);
    }

    /**
     * Root-relative URL of the cached still-frame thumbnail served by localkit.
     */
    public function thumbnailUrl(Device $device): string
    {
        return route('camera.thumbnail', ['device' => $device->getKey()], absolute: false);
    }

    /**
     * ffmpeg input arguments for the device's RTSP stream. Shared by the
     * thumbnail grab and the live remux so both agree on transport and
     * buffering.
     *
     * @return array<int, string>
     */
    public function ffmpegInput(Device $device): ?array
    {
        $url = $this->url($device);

        if ($url === null) {
            return null;
        }

        return [
            '-rtsp_transport', (string) config('camera.rtsp.transport'),
            '-fflags', 'nobuffer',
            '-flags', 'low_delay',
            '-i', $url,
        ];
    }

    private function deviceIp(Device $device): ?string
    {
        $ip = $device->configuration['states']['ipAddress'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
