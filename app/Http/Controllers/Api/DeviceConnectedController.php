<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceConnectedController
{

    public function __invoke(Request $request, string $serialNumber)
    {
        Log::info('DeviceConnectedController', $request->all());
        $device = Device::where('serial_number', $serialNumber)->firstOrFail();

        $connected = $request->json('connected') ?? 0;
        Log::info('DeviceConnectedController', ['state' => $connected]);


        $device->update(['mqtt_connected' => (int)$connected]);

        return response()->noContent(200);
    }
}
