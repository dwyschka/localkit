<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOnlyIotDeviceInfoResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevOnlyIotDeviceInfoController extends Controller
{

    public function __invoke(Request $request)
    {
        $device = Device::whereSerialNumber($request->get('sn'))->firstOrFail();

        return new DevOnlyIotDeviceInfoResource($device);
    }
}
