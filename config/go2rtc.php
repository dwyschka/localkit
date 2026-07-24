<?php
return [
    // Every HasCamera device runs its own go2rtc server on its local IP.
    // Streams are served directly from the device, not from a central instance.
    'port' => env('GO2RTC_DEVICE_PORT', 1984),
    'stream' => env('GO2RTC_DEVICE_STREAM', 'camera'),
];
