<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevIotDeviceInfoResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevIotDeviceInfoController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        if($device) {
            $type = PetkitHeader::deviceType($request->header('X-Device'));
            Log::info('GOt Request from DeviceType', ['type' => $type]);
            $device->update([
                'device_type' => $type,
            ]);
        }

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            $this->proxy($request);
        }
        return new DevIotDeviceInfoResource($device);
    }
}
