<?php

// Settings for the local S3-compatible storage (SeaweedFS), sourced from
// the "localkit-s3" filesystem disk so credentials/bucket/endpoint stay
// defined in one place (.env via config/filesystems.php).
return [
    'disk' => 'localkit-s3',

    'bucket' => config('filesystems.disks.localkit-s3.bucket'),

    // Physical devices reach the gateway over the LAN, not the docker
    // network, so the host comes from petkit.local_ip while the port is
    // parsed out of the disk's (docker-internal) endpoint.
    'port' => parse_url(config('filesystems.disks.localkit-s3.endpoint'), PHP_URL_PORT) ?: 8333,
];
