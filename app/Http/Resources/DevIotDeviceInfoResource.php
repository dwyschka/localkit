<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevIotDeviceInfoResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $iotInstanceId = 'iot-600a5gmp';

        return [
            'id' => $this->petkit_id,
            'deviceName' => sprintf('d_%s_%s', $this->device_type, $this->serial_number),
            'deviceSecret' => $this->secret,
            'iotInstanceId' => $iotInstanceId,
            'productKey' => $this->mqtt_subdomain ?? Str::of(md5($this->petkit_id))->substr(0, 10),
            'mqttHost' => sprintf('%s.mqtt.iothub.aliyuncs.com', $iotInstanceId),
            'createdAt' => $this->created_at->timestamp * 1000,
            'type' => 1,
            'regionId' => 'eu-central-1',
        ];
    }

}
