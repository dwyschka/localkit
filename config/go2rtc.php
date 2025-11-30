<?php
return [
    'petkit' => [
        'tserverUrl' => 'http://%s/main.ts?audio=1'
    ],
    'settings' => [
        "streams" => [
            "camera" => [
            ]
        ],
        "api" => [
            "listen" => ":1984",
            "cors" => [
                "allow_origin" => "*",
                "allow_methods" => "GET, POST, OPTIONS",
                "allow_headers" => "Content-Type"
            ]
        ],
        "webrtc" => [
            "listen" => ":8555"
        ],
        "log" => [
            "level" => "info"
        ]
    ]
];
