<?php

namespace App\Http\Middleware;

use App\Helpers\PetkitHeader;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogDeviceHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $device = $this->resolveDevice($request);

        if ($device && $device->debug_mode) {
            $this->log($request, $response, $device);
        }

        return $response;
    }

    private function resolveDevice(Request $request): ?Device
    {
        if ($sn = $request->input('sn')) {
            return Device::where('serial_number', $sn)->first();
        }

        if ($xDevice = $request->header('X-Device')) {
            try {
                $petkitId = PetkitHeader::petkitId($xDevice);
                return Device::where('petkit_id', $petkitId)->first();
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function log(Request $request, Response $response, Device $device): void
    {
        $channel = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/device_' . $device->serial_number . '.log'),
            'level' => 'debug',
        ]);

        $context = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'response_status' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
        ];

        Log::stack([$channel, 'default'])->debug('[HTTP] ' . $request->method() . ' ' . $request->path(), $context);
    }
}
