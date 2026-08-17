<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevDiscernConfigResource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevDiscernConfigController extends Controller
{
    public function __invoke(Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        return new DevDiscernConfigResource($device);
    }
}
