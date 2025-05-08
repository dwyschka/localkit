<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class DevOtaResource extends PetkitHttpResource
{
    public static $wrap = 'result';
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'firmwareId' => 33,
            'version' => '1.625',
            'details' => [
                [
                    'id' => 50,
                    'module' => 'userbin',
                    'version' => 2447004,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/63ab5fc6-38c0-4333-ad5b-24e202f52951.bin',
                        'size' => 1494992,
                        'digest' => '019598c95ebd6c9c2a0aafc8633edb1f'
                    ]
                ],
                [
                    'id' => 49,
                    'module' => 'pics',
                    'version' => 2442001,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/3b01a7e7-54b4-4fb1-981c-1d8a0d6af060.bin',
                        'size' => 131072,
                        'digest' => '238b72bd540037f4ee33bf5307684713'
                    ]
                ],
                [
                    'id' => 48,
                    'module' => 'lans',
                    'version' => 2444003,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/e19cef1a-f5a5-4ed2-8d44-4c2c78d11571.bin',
                        'size' => 712704,
                        'digest' => '36fb8f4ea82f5252d52ec73c9a10b319'
                    ]
                ]
            ]
        ];
    }
}
