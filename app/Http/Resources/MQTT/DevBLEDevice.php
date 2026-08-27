<?php

namespace App\Http\Resources\MQTT;

use App\Models\BluetoothDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevBLEDevice extends JsonResource
{

    public function toArray(Request $request)
    {
        $devices = BluetoothDevice::whereNot('type', 'k3')->get();
        return [
            "msgType" => 0,
            "payload" => [
                "nextTick" => 3600,
                "dataType" => "dev_ble_device",
                "list" => $devices->map(function ($device) {
                    return [
                        "interval" => $device->interval,
                        "id" => $device->petkit_id,
                        "secret" => $device->secret,
                        "type" => $device->bluetoothDeviceType(),
                        "mac" => $device->mac
                    ];
                })->toArray()
            ],
            "type" => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time()
        ];
    }

}
