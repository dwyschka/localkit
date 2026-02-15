<?php

namespace App\Http\Resources;

use App\Models\BluetoothDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevBleDeviceResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $devices = BluetoothDevice::whereNot('type', 'k3')->all();

        return [
            "list" => $devices->map(function ($device) {
                return [
                    "interval" => 240,
                    "id" => $device->petkit_id,
                    "secret" => $device->secret,
                    "type" => $device->bluetoothDeviceType(),
                    "mac" => $device->mac
                ];
            })->toArray(),
            "nextTick" => 3600
        ];
    }
}
