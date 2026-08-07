<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\HeartbeatOtaResource;
use App\Http\Resources\HeartbeatResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        if($device?->ota_state) {
            return new HeartbeatOtaResource($device);
        }

        $device->update([
            'last_heartbeat' => time()
        ]);

        Log::info('Heartbeat', ['device' => $deviceId, 'request' => $request->all()]);
        return new HeartbeatResource($device);
    }
}
