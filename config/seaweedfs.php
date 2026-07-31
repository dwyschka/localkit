<?php

return [
    'bucket' => env('AWS_BUCKET', 'localkit'),

    // Port the SeaweedFS S3 gateway is published on, reachable via petkit.local_ip
    // since physical devices talk to it directly, not through the docker network.
    's3_port' => env('SEAWEEDFS_S3_PORT', 8333),
];
