<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevOnlyIotDeviceInfoResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productKey = $this->mqtt_subdomain ?? Str::of(md5($this->petkit_id))->substr(0, 10);
        $region = 'eu-central-1';

        return [
            'ali' => [
                'id' => $this->petkit_id,
                'deviceName' => sprintf('d_%s_%s', $this->device_type, $this->serial_number),
                'deviceSecret' => $this->secret ?? '',
                'iotPlatform' => 'ALI',
                'iotInstanceId' => $this->mqtt_subdomain,
                'productKey' => $productKey,
                'mqttHost' => sprintf('%s.iot-as-mqtt.%s.aliyuncs.com', $productKey, $region),
                'createdAt' => $this->created_at->timestamp * 1000,
                'type' => 1,
                'regionId' => $region
            ]
        ];
    }

}
