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

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            return $this->proxy($request);
        }

        if($device?->ota_state) {
            if($request->input('success') === '1') {
                $device->update([
                    'ota_state' => 0,
                    'ota_available' => 0
                ]);
                Log::info('Ota Complete', ['device' => $device->id]);
            } else {
                Log::info('Ota Start', ['device' => $device->id]);
            }

            }

            $data = ['result' => 'success'];
            $json = json_encode($data);
            return response($json, 200)
                ->header('Content-Type', 'application/json;charset=utf-8')
                ->header('Content-Length', mb_strlen($json, '8bit'));
                
    }
}
