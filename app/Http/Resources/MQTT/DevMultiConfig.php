<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevMultiConfig extends JsonResource
{
    public function toArray(Request $request)
    {
        $definition = $this->resource->definition();

        // The T5 owns the full range set (light/tone/disturb/camera/detect)
        // and its ctrl_t5 parser expects plain arrays there. Keep the legacy
        // two-key placeholder for every other type.
        $ranges = method_exists($definition, 'toScheduleGet')
            ? $definition->toDeviceMultiConfig()
            : [
                'lightMultiRange' => [
                    [0, 1440],
                ],
                'distrubMultiRange' => [
                    [40, 520],
                ],
            ];

        return [
            'msgType' => 0,
            'payload' => [
                'dataType' => 'dev_multi_config',
                ...$ranges,
            ],
            'type' => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time(),
        ];
    }
}
