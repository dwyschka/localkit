<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Illuminate\Http\Request;

class DevOtaCheckController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            return $this->proxy($request);
        }



        $obj = new \stdClass();
        $obj->result = new \stdClass();
        return new DevOtaCheckResource($obj);
    }
}
