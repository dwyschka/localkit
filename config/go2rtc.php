<?php
return [
    // Every HasCamera device runs its own go2rtc server on its local IP.
    // Streams are served directly from the device, not from a central instance.
    'port' => env('GO2RTC_DEVICE_PORT', 1984),
    'stream' => env('GO2RTC_DEVICE_STREAM', 'camera'),

    // Camera previews are shown as a cached still frame (grabbed with ffmpeg)
    // instead of a live stream.
    'thumbnail' => [
        'ttl' => (int) env('GO2RTC_THUMBNAIL_TTL', 30),        // cache seconds
        'timeout' => (int) env('GO2RTC_THUMBNAIL_TIMEOUT', 10), // ffmpeg timeout
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
    ],
];
