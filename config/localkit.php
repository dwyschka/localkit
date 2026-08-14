<?php

return [
    'firmware_proxy' => env('LOCALKIT_FIRMWARE_PROXY', false),
    'ota_repository' => env('OTA_REPOSITORY', 'https://tool.localkit.io'),

    /*
    |--------------------------------------------------------------------------
    | Device Object Storage (OCI / S3 emulation)
    |--------------------------------------------------------------------------
    |
    | PetKit camera devices (e.g. the D4s, deviceType 25) request short lived
    | upload credentials via `dev_oss_sts_info_new_v2` and then push their
    | event images, highlights, time lapses and video clips to object storage.
    |
    | Localkit answers with an OCI-shaped response but points every URL back at
    | itself. The `oci` emulation routes (see routes/web.php) then persist the
    | uploads to the `disk` below, which by default is the bundled Garage S3
    | service defined in docker-compose.yml.
    |
    */
    'storage' => [
        // Answer `dev_oss_sts_info_new_v2` with a real capability set instead
        // of an empty payload. Disable to keep the device from uploading.
        'enabled' => env('LOCALKIT_STORAGE_ENABLE', true),

        // Reported storage backend type. The device speaks plain HTTP PUT/GET
        // against the URLs below regardless of this label.
        'type' => env('LOCALKIT_STORAGE_TYPE', 'oci'),

        // Public base URL the *device* uses to reach the emulation endpoints.
        // Must be reachable from the camera. Defaults to the localkit host.
        'endpoint' => env('LOCALKIT_STORAGE_ENDPOINT', 'http://' . env('PETKIT_LOCAL_IP', '127.0.0.1')),

        // Cosmetic OCI namespace/bucket names echoed back in the response.
        'namespace' => env('LOCALKIT_STORAGE_NAMESPACE', 'localkit'),
        'bucket' => env('LOCALKIT_STORAGE_BUCKET', 'localkit'),

        // AES-128 key (16 chars) the device uses to encrypt uploaded objects.
        // Localkit stores the ciphertext as-is; it does not need to decrypt.
        'aes_key' => env('LOCALKIT_STORAGE_AES_KEY', 'ea8e77e149818f72'),

        // Decrypt objects in place as soon as dev_upload_file_info_v2 reports
        // their IV (see DevUploadFileInfoV2Controller). Off for now while the
        // object-key correlation (PUT upload -> fileId) is unreliable -
        // leaves objects encrypted on disk rather than risk decrypting the
        // wrong one.
        'decrypt_on_upload' => env('LOCALKIT_STORAGE_DECRYPT_ON_UPLOAD', false),

        // PetKit product type number echoed back when the device does not
        // report a numeric type itself (25 = YumShare Dual / d4sh).
        'device_type_code' => (int) env('LOCALKIT_STORAGE_DEVICE_TYPE', 25),

        // Validity windows handed to the device (seconds).
        'cycle_ttl' => (int) env('LOCALKIT_STORAGE_CYCLE_TTL', 30 * 24 * 3600),
        'par_ttl' => (int) env('LOCALKIT_STORAGE_PAR_TTL', 12 * 3600),

        // Capture cycle types the device is allowed to upload.
        'cycle_types' => [
            'eventImage',
            'highLight',
            'timeLapse',
            'dynamicVideo',
            'continuousVideo',
            'fullVideo',
        ],

        // Laravel filesystem disk the emulation routes read/write objects on.
        'disk' => env('LOCALKIT_STORAGE_DISK', 'localkit_storage'),
    ],
];
