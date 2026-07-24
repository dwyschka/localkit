<?php

namespace App\Management;

use App\Models\Device;

/**
 * Every HasCamera device runs its own go2rtc server on its local IP. This client
 * builds the URLs to reach that per-device server - there is no central go2rtc.
 */
class Go2RTC
{
    public function streamUrl(Device $device): string
    {
        return $this->url($device, '/stream.html');
    }

    public function frameUrl(Device $device): string
    {
        return $this->url($device, '/api/frame.jpeg');
    }

    private function url(Device $device, string $path): string
    {
        return sprintf(
            'http://%s:%d%s?src=%s',
            $this->deviceIp($device),
            config('go2rtc.port'),
            $path,
            config('go2rtc.stream')
        );
    }

    private function deviceIp(Device $device): ?string
    {
        return $device->configuration['states']['ipAddress'] ?? null;
    }
}
