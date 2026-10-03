<?php
return [
    /*
     * Camera equipped devices expose a plain RTSP server on their local IP -
     * there is no WebRTC, no HTTP API and no stream index to query. The URL is
     * always rtsp://<ip>:8554/stream0, so it is derived from the device's IP
     * rather than discovered.
     */
    'rtsp' => [
        'port' => (int) env('CAMERA_RTSP_PORT', 8554),
        'path' => env('CAMERA_RTSP_PATH', 'stream0'),

        // RTSP over TCP - UDP loses packets on busy WiFi and the resulting
        // corrupt frames are worse than the slightly higher latency.
        'transport' => env('CAMERA_RTSP_TRANSPORT', 'tcp'),
    ],

    'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),

    // Camera previews (device list, Media section) are a cached still frame
    // grabbed from RTSP with ffmpeg instead of a live stream.
    'thumbnail' => [
        'ttl' => (int) env('CAMERA_THUMBNAIL_TTL', 10),        // cache seconds
        'timeout' => (int) env('CAMERA_THUMBNAIL_TIMEOUT', 10), // ffmpeg timeout
    ],

    // Live view: ffmpeg remuxes RTSP into MPEG-TS which mpegts.js plays in the
    // browser (see public/js/localkit/camera-stream.js).
    'live' => [
        // Video is passed through untouched; audio has to be re-encoded to AAC
        // because the devices send G.711/PCM, which MSE cannot decode. Set
        // false to drop audio entirely.
        'audio' => (bool) env('CAMERA_LIVE_AUDIO', true),

        // Hard ceiling per viewer, in seconds. The stream normally ends when
        // the browser disconnects; this only catches connections that never do.
        'max_duration' => (int) env('CAMERA_LIVE_MAX_DURATION', 3600),
    ],
];
