<?php

namespace App\Http\Controllers\Petkit;

use Exception;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevSignupResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevSignupController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {


        $update = [
            'firmware' => $request->get('firmware'),
            'mac' => $request->get('mac'),
            'timezone' => 1.0,
            'locale' => $request->get('locale'),
            'bt_mac' => $request->get('bt_mac'),
            'ap_mac' => $request->get('ap_mac'),
            'chip_id' => $request->get('chipid'),
            'device_type' => $deviceType,
        ];

        Log::info('DevSignupController', $update);

        if($request->get('id')) {
            $update['petkit_id'] = $request->get('id');
        }

        /** @var Device $device */
        $device = Device::updateOrCreate([
            'serial_number' => $request->get('sn'),
        ], $update);

        if (empty($device->secret) || empty($device->mqtt_subdomain)) {
            $device->update([
                'secret' => $device->secret ?: Str::substr(md5(Str::random(16)), 0, 16),
                'mqtt_subdomain' => $device->mqtt_subdomain ?: 'localkit',
            ]);
        }

        try {
            $device->update([
                'configuration' => $device->configuration()->toArray(),
            ]);
        } catch (Exception $e) {
            return new JsonResponse(['result' => 'error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        $device->refresh();

        return new DevSignupResource($device);
    }
}
