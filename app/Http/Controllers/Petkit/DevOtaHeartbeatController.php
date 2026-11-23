<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * `dev_ota_heartbeat` - sent periodically while an OTA is running
 * (hardware/firmware/firmwareDetails form body). ctrl_t5 ignores the
 * response body and only needs a 200, so keep this deliberately simple.
 */
class DevOtaHeartbeatController extends Controller
{
    public function __invoke(string $deviceType, Request $request): JsonResponse
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        Log::info('OTA heartbeat', [
            'device' => $device?->id,
            'deviceType' => $deviceType,
            'data' => $request->except(['firmwareDetails']),
        ]);

        return response()->json(['result' => 'success']);
    }
}
