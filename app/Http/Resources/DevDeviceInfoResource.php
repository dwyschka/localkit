<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevDeviceInfoResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $config = $this->resource->configuration['settings'];

        return [
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
                'relateK3Switch' => (int)$config['relateK3Switch'] ?? 0,
                'lightest' =>$config['lightest'],
                'deepClean' => (int)$config['deepClean'],
                'removeSand' => (int)$config['removeSand'],
                'bury' => (int)$config['bury'],
            ],
            'petInTipLimit' => (int)$config['petInTipLimit'],
        ];
    }
}
