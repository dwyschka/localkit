<?php

namespace App\Petkit\Storage;

use App\Models\Device;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Emulates the PetKit cloud object storage (`dev_oss_sts_info_new_v2`).
 *
 * The camera asks for short lived upload credentials per capture "cycle"
 * (event image, highlight, time lapse, several video flavours) and then pushes
 * the encrypted objects to the returned URLs. We answer with an OCI-shaped
 * payload whose URLs all point back at localkit's own emulation routes, so the
 * uploads land on the configured Garage S3 disk instead of PetKit's cloud.
 */
class DeviceObjectStorage
{
    /**
     * Build the full `dev_oss_sts_info_new_v2` result payload for a device.
     */
    public function capabilityResponse(string $deviceType, Device $device): array
    {
        $deviceId = (int) $device->petkit_id;
        $pathPrefix = $deviceType . '/' . $deviceId;
        $typeCode = $this->deviceTypeCode($device);

        $now = now();
        $cycleExpiration = $now->copy()->addSeconds((int) config('localkit.storage.cycle_ttl'))->timestamp;
        $parExpirationMs = $now->copy()->addSeconds((int) config('localkit.storage.par_ttl'))->timestamp * 1000;

        $bucket = (string) config('localkit.storage.bucket');
        $domain = $this->domainUrl();
        $parUrl = $this->parUrl();
        $aesKeyStr = (string) config('localkit.storage.aes_key');
        $aesKeyUri = $this->aesKeyObject($pathPrefix, $aesKeyStr);

        // The firmware's upload routine always speaks Aliyun's OSS protocol
        // (Authorization: OSS <key>:<sig>, x-oss-security-token,
        // x-oss-object-acl) regardless of whether it's addressing "primary"
        // or "standby" - both need non-empty credentials or the client-side
        // STS response parsing fails before it even attempts the upload.
        // ObjectStorageController never verifies these, so they're opaque
        // per-request placeholders, not real credentials - the actual
        // Garage/S3 key/secret never leave the server.
        $accessKeyId = 'STS.' . Str::random(20);
        $accessKeySecret = Str::random(30);
        $securityToken = Str::random(64);

        $capabilities = [];
        foreach ((array) config('localkit.storage.cycle_types') as $cycleType) {
            $capabilities[] = [
                'deviceId' => $deviceId,
                'deviceType' => $typeCode,
                'cycleType' => $cycleType,
                'cycle' => 1,
                'cycleExpiration' => $cycleExpiration,
                'pathPrefix' => $pathPrefix,
                'primaryAesKeyStr' => $aesKeyStr,
                'primaryAesKeyUri' => $aesKeyUri,
                'primaryBucketName' => $bucket,
                'primaryDomain' => $domain,
                'primaryParUrl' => $parUrl,
                'primaryParExpiration' => $parExpirationMs,
                'primaryAccessKeyId' => $accessKeyId,
                'primaryAccessKeySecret' => $accessKeySecret,
                'primarySecurityToken' => $securityToken,
                'standbyBucketName' => $bucket,
                'standbyDomain' => $domain,
                'standbyParUrl' => $parUrl,
                'standbyParExpiration' => $parExpirationMs,
                'standbyAesKeyStr' => $aesKeyStr,
                'standbyAesKeyUri' => $aesKeyUri,
                'standbyAccessKeyId' => $accessKeyId,
                'standbyAccessKeySecret' => $accessKeySecret,
                'standbySecurityToken' => $securityToken,
                'isHD' => 0,
            ];
        }

        return [
            'type' => (string) config('localkit.storage.type'),
            'capability' => $capabilities,
        ];
    }

    /**
     * The Laravel disk objects are persisted to.
     */
    public function disk(): Filesystem
    {
        return Storage::disk((string) config('localkit.storage.disk'));
    }

    /**
     * Base URL the device reads objects from (ends with `/o/`).
     */
    public function domainUrl(): string
    {
        return sprintf(
            '%s/oci/n/%s/b/%s/o/',
            $this->endpoint(),
            rawurlencode((string) config('localkit.storage.namespace')),
            rawurlencode((string) config('localkit.storage.bucket')),
        );
    }

    /**
     * Base pre-authenticated URL the device writes objects to (ends with `/o/`).
     */
    public function parUrl(): string
    {
        return sprintf(
            '%s/oci/p/%s/n/%s/b/%s/o/',
            $this->endpoint(),
            Str::random(48),
            rawurlencode((string) config('localkit.storage.namespace')),
            rawurlencode((string) config('localkit.storage.bucket')),
        );
    }

    /**
     * Hardcoded top-level bucket folder AES key objects are stored under.
     * Doesn't matter to us (we never read it back), but the real PetKit
     * cloud apparently keys its own layout on it, so mirror it.
     */
    protected const AES_KEYS_FOLDER = 'aeskeys';

    /**
     * Store the AES key alongside the objects and return the URL to read it.
     */
    protected function aesKeyObject(string $pathPrefix, string $aesKeyStr): string
    {
        $object = sprintf('%s/%s/%s/%d.txt', self::AES_KEYS_FOLDER, $pathPrefix, Str::random(19), now()->timestamp * 1000);

        $this->disk()->put($object, $aesKeyStr);

        return $this->domainUrl() . $object;
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('localkit.storage.endpoint'), '/');
    }

    /**
     * PetKit product type number echoed back to the device (cosmetic here).
     */
    protected function deviceTypeCode(Device $device): int
    {
        return is_numeric($device->device_type)
            ? (int) $device->device_type
            : (int) config('localkit.storage.device_type_code', 25);
    }
}
