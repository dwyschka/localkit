<?php

namespace App\Http\Resources\API;

use App\Models\Device;
use App\Petkit\DeviceDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TopicApiResource extends JsonResource
{
    public static $wrap = 'data';


    public function toArray(Request $request)
    {
        /** @var DeviceDefinition $definition */
        $definition = $this->resource->definition();
        return [
            'serialNumber' => $this->serial_number,
            'topics' => $definition->subscribedTopics()
        ];
    }

}
