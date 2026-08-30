<?php

namespace App\Http\Controllers\Petkit;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Petkit\DeviceStates;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class DevOtaStartController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        $deviceId = PetkitHeader::petkitId($request->header('X-Device'));
        $device = Device::wherePetkitId($deviceId)->first();

        Log::info('OTA', [$request->toArray()]);

        if ($device?->ota_state) {
            $device->update([
                'working_state' => DeviceStates::UPDATING->value,
            ]);
            Log::info('Ota Start', ['device' => $device->id]);
        }

        $data = ['result' => 'success'];
        $json = json_encode($data);

        return response($json, 200)
            ->header('Content-Type', 'application/json;charset=utf-8')
            ->header('Content-Length', mb_strlen($json, '8bit'));
    }
}
