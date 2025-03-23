<?php

namespace App\Http\Resources\MQTT;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DevDeviceInfo extends JsonResource
{

    public function toArray(Request $request)
    {
        $config = $this->resource->configuration['settings'];

        return [
            'msgType' => 0,
            'payload' => [
                'dataType' => 'dev_device_info',
                'device' => [
                    'id' => $this->petkitId,
                    'mac' => $this->mac,
                    'sn' => $this->serialNumber,
                    'secret' => '',
                    'timezone' => $this->timezone,
                    'locale' => $this->locale,
                    'shareOpen' => (int)$config['shareOpen'],
                    'typeCode' => (int)$config['typeCode'],
                    'withK3' => (int)$config['withK3'],
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
                        'disturbMode' => (int)$config['disturbMode'],
                        'disturbRange' =>$config['disturbRange'],
                        'sandSetUseConfig' =>$config['sandSetUseConfig'],
                        'k3Config' => $config['k3Config'],
                        'relateK3Switch' => (int)$config['hasK3'],
                        'lightest' =>$config['lightest'],
                        'deepClean' => (int)$config['deepClean'],
                        'removeSand' => (int)$config['removeSand'],
                        'bury' => (int)$config['bury'],
                    ],
                    'multiConfig' => 0,
                    'petInTipLimit' => (int)$config['petInTipLimit'],
                ],
            ],
            'type' => 't4_data_get',
            'timestamp' => time()
        ];
    }

}
