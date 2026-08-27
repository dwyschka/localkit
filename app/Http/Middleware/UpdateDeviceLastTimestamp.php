<?php

namespace App\Http\Middleware;

use Throwable;
use App\Helpers\PetkitHeader;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateDeviceLastTimestamp
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($xDevice = $request->header('X-Device')) {
            try {
                $timestamp = PetkitHeader::timestamp($xDevice);

                if ($timestamp !== null) {
                    // Mass update via the query builder, not $device->update(), so this
                    // doesn't fire Device::booted()'s 'updated' hook (Home Assistant
                    // republish) on every single device HTTP call.
                    Device::where('petkit_id', PetkitHeader::petkitId($xDevice))
                        ->update(['last_timestamp' => $timestamp]);
                }
            } catch (Throwable) {
            }
        }

        return $next($request);
    }
}
