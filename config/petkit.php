<?php

return [
    'local_ip' =>  env('PETKIT_LOCAL_IP', '127.0.0.1'),
    'discovery_prefix' => env('HOMEASSISTANT_DISCOVERY_PREFIX', 'homeassistant'),
    'bypass_auth' => env('BYPASS_AUTH', true),
    'bypass_auth_id' => env('BYPASS_AUTH_ID', 1),
    'homeassistant' => [
        'enabled' => env('HOMEASSISTANT_ENABLED', false),
    ],

    // Root telnet credentials for NextGen devices' built-in telnetd (see
    // DeviceActions::actions()'s "Reboot (Telnet)" action) - no default
    // here deliberately, set these in your own untracked .env.
    'telnet_username' => env('DEVICE_TELNET_USERNAME'),
    'telnet_password' => env('DEVICE_TELNET_PASSWORD'),

    // When enabled, DevDiscernPicController serves discern.txt (a captured
    // real cloud response placed at the project root) verbatim instead of
    // our own generated payload - lets you A/B a known-good response
    // against ours while pet recognition is still misbehaving.
    'fake_discern_req' => env('FAKE_DISCERN_REQ', false),
];
