<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read int $deviceId
 * @property-read int $deviceType
 * @property-read string $cycleType
 * @property-read string $pathPrefix
 * @property-read string $aesKey
 * @property-read string $bucket
 * @property-read string $endpoint
 * @property-read \Illuminate\Support\Carbon $expiration
 */
class DevOssCapabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $aesKeyUri = $this->endpoint . $this->pathPrefix . '/aes-key.txt';

        return [
            'deviceId' => $this->deviceId,
            'deviceType' => $this->deviceType,
            'cycleType' => $this->cycleType,
            'cycle' => 1,
            'cycleExpiration' => $this->expiration->timestamp,
            'pathPrefix' => $this->pathPrefix,
            'primaryAesKeyStr' => $this->aesKey,
            'primaryAesKeyUri' => $aesKeyUri,
            'primaryBucketName' => $this->bucket,
            'primaryDomain' => $this->endpoint,
            'primaryParUrl' => $this->endpoint,
            'primaryParExpiration' => $this->expiration->timestamp * 1000,
            'standbyBucketName' => $this->bucket,
            'standbyDomain' => $this->endpoint,
            'standbyParUrl' => $this->endpoint,
            'standbyParExpiration' => $this->expiration->timestamp * 1000,
            'standbyAesKeyStr' => $this->aesKey,
            'standbyAesKeyUri' => $aesKeyUri,
            'isHD' => 0,
        ];
    }
}
