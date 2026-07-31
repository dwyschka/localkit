<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DevOssStsInfoNewV2Resource extends PetkitHttpResource
{
    protected const CYCLE_TYPES = [
        'eventImage',
        'highLight',
        'timeLapse',
        'dynamicVideo',
        'continuousVideo',
        'fullVideo',
    ];

    // D4SH gen2 is the only HasCamera device today; this is the numeric
    // deviceType Petkit's own cloud returns for it in this payload.
    protected const DEVICE_TYPE_CODE = 25;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $endpoint = sprintf(
            'http://%s:%d/%s/',
            config('petkit.local_ip'),
            config('seaweedfs.s3_port'),
            config('seaweedfs.bucket')
        );

        $pathPrefix = sprintf('d4sh/%s', $this->petkit_id);
        $aesKey = (string) Str::of(md5($this->petkit_id))->substr(0, 16);
        $expiration = now()->addYear();

        $capability = array_map(fn (string $cycleType) => [
            'deviceId' => (int) $this->petkit_id,
            'deviceType' => self::DEVICE_TYPE_CODE,
            'cycleType' => $cycleType,
            'cycle' => 1,
            'cycleExpiration' => $expiration->timestamp,
            'pathPrefix' => $pathPrefix,
            'primaryAesKeyStr' => $aesKey,
            'primaryAesKeyUri' => $endpoint . $pathPrefix . '/aes-key.txt',
            'primaryBucketName' => config('seaweedfs.bucket'),
            'primaryDomain' => $endpoint,
            'primaryParUrl' => $endpoint,
            'primaryParExpiration' => $expiration->timestamp * 1000,
            'standbyBucketName' => config('seaweedfs.bucket'),
            'standbyDomain' => $endpoint,
            'standbyParUrl' => $endpoint,
            'standbyParExpiration' => $expiration->timestamp * 1000,
            'standbyAesKeyStr' => $aesKey,
            'standbyAesKeyUri' => $endpoint . $pathPrefix . '/aes-key.txt',
            'isHD' => 0,
        ], self::CYCLE_TYPES);

        return [
            'type' => 'oci',
            'capability' => $capability,
        ];
    }
}
