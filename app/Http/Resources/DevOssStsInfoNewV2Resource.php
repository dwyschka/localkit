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
            config('seaweedfs.port'),
            config('seaweedfs.bucket')
        );

        $pathPrefix = sprintf('d4sh/%s', $this->petkit_id);
        $aesKey = (string) Str::of(md5($this->petkit_id))->substr(0, 16);
        $expiration = now()->addYear();
        $deviceCode = $this->deviceCode();

        $capabilities = collect(self::CYCLE_TYPES)->map(fn (string $cycleType) => (object) [
            'deviceId' => (int) $this->petkit_id,
            'deviceType' => $deviceCode,
            'cycleType' => $cycleType,
            'pathPrefix' => $pathPrefix,
            'aesKey' => $aesKey,
            'bucket' => config('seaweedfs.bucket'),
            'endpoint' => $endpoint,
            'expiration' => $expiration,
        ]);

        return [
            'type' => 'oci',
            'capability' => DevOssCapabilityResource::collection($capabilities)->toArray($request),
        ];
    }
}
