<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevScheduleGetResource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevScheduleGetController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();
        return new DevScheduleGetResource($device);
    }
}
