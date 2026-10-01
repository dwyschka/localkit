<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A heartbeat answer that tells the device to bring its telnetd up.
 *
 * Delivered while a device carries the `provisioning` flag (set when a device
 * provisioned over BLE first signs up). The device runs `content` as a root
 * shell command via its `user_cmd.run_cmd` path - the same channel the
 * reference project's patchers use, confirmed to execute on this firmware
 * family.
 *
 * The command itself lives in config (`localkit.provisioning.telnet_command`).
 * It is idempotent: it only starts telnetd if none is running, so re-sending it
 * every heartbeat - and after any device reboot - never stacks processes. Plain
 * `telnetd` (no `-l`) keeps the device's own login prompt, so the
 * DEVICE_TELNET_USERNAME / DEVICE_TELNET_PASSWORD credentials the Reboot
 * (Telnet) action already uses apply unchanged.
 *
 * Two firmware rules on heartbeat entries, read out of `net_http_heartbeat`:
 * an entry is dropped if its `timestamp` is not greater than the last one the
 * device consumed, and dropped again if `time/1000 - timestamp >= 61`. A single
 * entry stamped with the current second satisfies both.
 */
class HeartbeatTelnetResource extends PetkitHttpResource
{
    public static $wrap = 'result';

    public function toArray(Request $request): array
    {
        $timestamp = time();

        return [
            [
                'content' => json_encode([
                    'msgType' => 0,
                    'user_cmd' => [
                        'run_cmd' => config('localkit.provisioning.telnet_command'),
                    ],
                ]),
                'time' => $timestamp * 1000,
                'timestamp' => $timestamp,
            ],
        ];
    }
}
