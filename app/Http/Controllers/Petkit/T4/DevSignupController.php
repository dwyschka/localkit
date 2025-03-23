<?php

namespace App\Http\Controllers\Petkit\T4;

use App\Helpers\PetkitHeader;
use App\Http\Controllers\Controller;
use App\Http\Resources\DevSignupResource;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevSignupController extends Controller
{

    public function __invoke(string $deviceType, Request $request)
    {
        $device = Device::updateOrCreate([
            'serial_number' => $request->get('sn'),
        ], [
            'firmware' => $request->get('firmware'),
            'mac' => $request->get('mac'),
            'timezone' => $request->get('timezone'),
            'locale' => $request->get('locale'),
            'petkit_id' => $request->get('id'),
            'bt_mac' => $request->get('bt_mac'),
            'ap_mac' => $request->get('ap_mac'),
            'chip_id' => $request->get('chipid'),
            'device_type' => $deviceType,
        ]);

        if(is_null($device) || ($device?->proxy_mode ?? 1)) {
            $this->proxy($request);
        }

        $device->update([
            'configuration' => array_merge($device->definition()->defaultConfiguration(), $device->configuration ?? [])
        ]);

        $device->refresh();
        return new DevSignupResource($device);
    }
}
