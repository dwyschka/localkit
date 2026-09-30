<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevServerInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        // Same server in both lists, for the same reason as the HTTP answer in
        // DevServerinfoResource: PetKit's hostname only resolves to Localkit
        // where DNS has been redirected, and a BLE-provisioned device has no
        // redirect. Handing it out over MQTT would undo the HTTP answer.
        $server = sprintf('http://%s/6/', config('petkit.local_ip'));

        return [
            "msgType" => 0,
            "payload" => [
                "dataType" => "dev_serverinfo",
                'ipServers' => [$server],
                'apiServers' => [$server],
                'nextTick' => 3600,
                'linked' => 1
            ],
            "type" => sprintf('%s_data_get', $this->resource->device_type),
            'timestamp' => time()
        ];
    }

}
