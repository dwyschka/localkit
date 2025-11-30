<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevDeviceInfoResource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevDeviceInfoController extends Controller
{
    public function __invoke(Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            $this->proxy($request);
        }

        return new DevDeviceInfoResource($device);
    }
}
