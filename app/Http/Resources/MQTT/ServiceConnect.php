<?php

namespace App\Http\Resources\MQTT;

use AllowDynamicProperties;
use App\Models\BluetoothDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceConnect extends JsonResource
{
    protected BluetoothDevice $bluetoothDevice;
    public function setBluetoothDevice(BluetoothDevice $bluetoothDevice): self
    {
        $this->bluetoothDevice = $bluetoothDevice;
        return $this;
    }

    public function toArray(Request $request)
    {
        return [
            "method" => 'thing.service.connect',
            'id' => (string)time(),
            "params" => [
                "connect_action" => 1,
                "device" => [
                    "type" => $this->bluetoothDevice->bluetoothDeviceType(),
                    'mac' => $this->bluetoothDevice->mac
                ],
                "timestamp" => time()
            ],
            "version" => "1.0.0",
        ];
    }

}
