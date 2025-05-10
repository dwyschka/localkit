<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevDeviceInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        $config = $this->resource->configuration['settings'];
        $k3 = $this->resource->configuration['k3Device'] ?? false;

        return [
            'msgType' => 0,
            'payload' => [
                'dataType' => 'dev_device_info',
                'device' => [
                    'id' => $this->petkit_id,
                    'mac' => $this->mac,
                    'sn' => $this->serial_number,
                    'secret' => '',
                    'timezone' => $this->timezone,
                    'locale' => $this->locale,
                    'shareOpen' => (int)$config['shareOpen'],
                    'typeCode' => (int)$config['typeCode'],
                    'withK3' => (int)isset($k3['id']),
                    'k3Id' => (int)($k3['id'] ?? 0),
                    'btMac' => $this->btMac,
                    'settings' => [
                        'sandType' => (int)$config['sandType'],
                        'manualLock' => (int)$config['manualLock'],
                        'lightMode' => (int)$config['lightMode'],
                        'clickOkEnable' => (int)$config['clickOkEnable'],
                        'lightRange' =>$config['lightRange'],
                        'autoWork' => (int)$config['autoWork'],
                        'fixedTimeClear' =>$config['fixedTimeClear'],
                        'downpos' => (int)$config['downpos'],
                        'deepRefresh' => (int)$config['deepRefresh'],
                        'autoIntervalMin' =>$config['autoIntervalMin'],
                        'stillTime' =>$config['stillTime'],
                        'unit' => (int)$config['unit'],
                        'language' =>$config['language'],
                        'avoidRepeat' => (int)$config['avoidRepeat'],
                        'underweight' => (int)$config['underweight'],
                        'kitten' => (int)$config['kitten'],
                        'stopTime' =>$config['stopTime'],
                        'sandFullWeight' => $config['sandFullWeight'],
                        'autoRefresh' => ($k3['id'] ?? 0) > 0 ? 1 : 0,
                        'disturbMode' => (int)$config['disturbMode'],
                        'disturbRange' =>$config['disturbRange'],
                        'sandSetUseConfig' =>$config['sandSetUseConfig'],
                        'k3Config' => $config['k3Config'],
                        'relateK3Switch' => (int)$config['relateK3Switch'] ?? 0,
                        'lightest' =>$config['lightest'],
                        'deepClean' => (int)$config['deepClean'],
                        'removeSand' => (int)$config['removeSand'],
                        'bury' => (int)$config['bury'],
                    ],
                    'k3Device' => [
                      'id' => (int)($k3['id'] ?? 0),
                      'mac' => $k3['mac'] ?? '',
                      'sn' => $k3['sn'] ?? '',
                      'secret' => $k3['secret'] ?? '',
                    ],
                    'multiConfig' => (bool)($k3['id'] ?? 0) > 0,
                    'petInTipLimit' => (int)$config['petInTipLimit'],
                ],
            ],
            'type' => 't4_data_get',
            'timestamp' => time()
        ];
    }

}
