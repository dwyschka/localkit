<?php

// Settings for the SeaweedFS container defined in docker-compose.yml /
// seaweedfs/s3.json. Intentionally standalone - not wired into
// config/filesystems.php, since this app only ever talks to it to build
// the device-facing upload endpoint, not through Laravel's Storage layer.
return [
    'bucket' => env('SEAWEEDFS_BUCKET', 'localkit'),

    // Physical devices reach the gateway over the LAN, not the docker
    // network, so the host comes from petkit.local_ip.
    'port' => env('SEAWEEDFS_S3_PORT', 8333),
];
