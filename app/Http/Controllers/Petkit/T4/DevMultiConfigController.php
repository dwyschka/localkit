<?php

namespace App\Http\Controllers\Petkit\T4;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevMultiConfigResource;
use App\Http\Resources\DevSignupResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevMultiConfigController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            $this->proxy($request);
        }

        return new DevMultiConfigResource($device);
    }
}
