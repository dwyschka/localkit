<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevOtaCheckResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevOtaController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        Log::info('OTA', [$request->toArray()]);
        if($device?->ota_state) {

            Log::info('Ota Start', ['device' => $device->id]);
            return new JsonResponse([
                'result' => 'success'
            ], 200, [], JSON_UNESCAPED_SLASHES);
        }

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            return $this->proxy($request);
        }

        return new JsonResponse([
            'result' => 'success'
        ]);
    }
}
