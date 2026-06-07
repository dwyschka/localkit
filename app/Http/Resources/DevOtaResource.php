<?php

namespace App\Http\Resources;

use App\Localkit\OTA;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DevOtaResource extends PetkitHttpResource
{
    public static $wrap = null;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'firmwareId' => 1020,
            'version' => '1.267',
            'details' => [
                [
                    'id' => 1024,
                    'module' => 'userbin',
                    'version' => 2531005,
                    'file' => [
                        'url' => 'http://petkit-storage-firmware-prod-eu.oss-eu-central-1.aliyuncs.com/2025/7/29/deaec50a-1e6e-47cb-9c03-b78aa2eeeb63.bin',
                        'size' => 1363200,
                        'digest' => 'a9a2978daed4546f0e8cf40a5cb62295',
                    ],
                ],
            ],
        ];
        return app(OTA::class)->toOta($this->resource);
    }

}
