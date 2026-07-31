<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOssStsInfoNewV2Resource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevOssStsInfoNewV2Controller extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        if (is_null($device) || ($device->proxy_mode ?? 1) || !$device->isNextGen()) {
            $this->proxy($request);
        }

        return new DevOssStsInfoNewV2Resource($device);
    }
}
