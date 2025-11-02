<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevSignupResource;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class DevSignupController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {

        $update = [
            'firmware' => $request->get('firmware'),
            'mac' => $request->get('mac'),
            'timezone' => $request->get('timezone'),
            'locale' => $request->get('locale'),
            'bt_mac' => $request->get('bt_mac'),
            'ap_mac' => $request->get('ap_mac'),
            'chip_id' => $request->get('chipid'),
            'device_type' => $deviceType,
        ];

        if($request->get('id')) {
            $update['petkit_id'] = $request->get('id');
        }

        $device = Device::updateOrCreate([
            'serial_number' => $request->get('sn'),
        ], $update);

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            return $this->proxy($request);
        }

        $config = Arr::mergeRecursiveDistinct($device->definition()->defaultConfiguration(), $device->configuration ?? []);

        dd(
            $device->definition()->defaultConfiguration(),
            $device->configuration,
            $config
        );
        $device->update([
            'configuration' => $config
        ]);

        $device->refresh();
        return new DevSignupResource($device);
    }
}
