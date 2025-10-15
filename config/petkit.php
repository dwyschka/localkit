<?php

return [
    'local_ip' =>  env('PETKIT_LOCAL_IP', '127.0.0.1'),
    'discovery_prefix' => env('HOMEASSISTANT_DISCOVERY_PREFIX', 'homeassistant'),
    'bypass_auth' => env('BYPASS_AUTH', true),
    'bypass_auth_id' => env('BYPASS_AUTH_ID', 1),
    'homeassistant' => [
        'enabled' => env('HOMEASSISTANT_ENABLED', false),
    ]
];
