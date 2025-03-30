<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevServerinfoResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevServerinfoController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->firstOrFail();

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {

            Log::info($deviceId, ['proxy']);
            $this->proxy($request);
        }

        return new DevServerinfoResource($device);
    }
}
