<?php

namespace App\Petkit\Interfaces;

/**
 * Marks a device configuration whose device has a camera. Those devices serve
 * a single RTSP stream on their local IP (see {@see \App\Management\Rtsp}) and
 * carry the two camera related state properties below.
 *
 * @property ?string $ipAddress    The device's address on the LAN; without it there is no stream.
 * @property ?string $lastSnapshot Filename of the most recent still frame on the `snapshots` disk.
 * @property ?string $stream       The device's RTSP URL, published to Home Assistant. Derived from $ipAddress.
 */
interface HasCamera
{

}
