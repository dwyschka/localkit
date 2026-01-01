<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevOtaController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        if($device?->ota_state) {
            return new JsonResponse([
                'result' => 'success'
            ]);
        }

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            return $this->proxy($request);
        }

        return new JsonResponse([
            'result' => 'success'
        ]);
    }
}
