<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevK3DeviceInfoResource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevK3DeviceInfoController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return new DevK3DeviceInfoResource($device);
    }
}
