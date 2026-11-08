<?php

namespace App\Petkit\BluetoothDevices;

use stdClass;
use App\Models\BluetoothDevice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Holds BLE writes until the proxy is connected to the peripheral.
 *
 * A proxy only relays `thing/service/ble` writes while it holds a connection
 * to the peripheral; otherwise they're silently dropped. On a D4 relaying a
 * W5, `thing/service/connect` is answered ~5s later by `ble_relay_over` with
 * action 1 / result 0, and the D4 closes the link itself ~2 min later with
 * another `ble_relay_over`, action 0.
 *
 * State lives in the cache because writes start in one process (a web
 * request or the Home Assistant listener) while the proxy's relay events
 * arrive in another (the MQTT listener).
 */
class RelayConnection
{
    /** Comfortably inside the ~2 min the proxy keeps a link open. */
    public const OPEN_SECONDS = 100;

    /** A connect normally completes in ~5s; after this it's presumed lost. */
    public const CONNECTING_SECONDS = 20;

    /** How long a queued write waits for the connection before it's dropped. */
    public const PENDING_SECONDS = 120;

    public static function isOpen(string $mac): bool
    {
        return Cache::has(self::key($mac, 'open'));
    }

    /**
     * Queues a write until the proxy reports the connection is up.
     *
     * @return bool true if the caller should ask the proxy to connect now.
     */
    public static function queue(string $mac, string $commandBase64, int $cmd): bool
    {
        return Cache::lock(self::key($mac, 'lock'), 5)->block(3, function () use ($mac, $commandBase64, $cmd) {
            $pending = Cache::get(self::key($mac, 'pending'), []);
            $pending[] = ['data' => $commandBase64, 'cmd' => $cmd];
            Cache::put(self::key($mac, 'pending'), $pending, self::PENDING_SECONDS);

            if (Cache::has(self::key($mac, 'connecting'))) {
                return false;
            }

            Cache::put(self::key($mac, 'connecting'), true, self::CONNECTING_SECONDS);

            return true;
        });
    }

    /**
     * Handles a proxy's `ble_relay_over` event content: on connect, sends
     * whatever is queued through that proxy.
     */
    public static function relayOver(stdClass $content, BluetoothProxyInterface $proxy): void
    {
        $mac = $content->device->mac ?? null;
        if ($mac === null) {
            return;
        }

        Cache::forget(self::key($mac, 'connecting'));

        $connected = (int) ($content->action ?? -1) === 1 && (int) ($content->result ?? -1) === 0;

        // The MQTT listener this runs in swallows exceptions from topic
        // handlers, so log rather than let a failed flush vanish.
        try {
            $pending = Cache::lock(self::key($mac, 'lock'), 5)->block(3, fn () => Cache::pull(self::key($mac, 'pending'), []));

            if (!$connected) {
                Cache::forget(self::key($mac, 'open'));
                if ($pending !== []) {
                    Log::warning('BLE relay not connected, dropping queued writes', [
                        'mac' => $mac,
                        'action' => $content->action ?? null,
                        'result' => $content->result ?? null,
                        'dropped' => count($pending),
                    ]);
                }
                return;
            }

            Cache::put(self::key($mac, 'open'), true, self::OPEN_SECONDS);

            if ($pending === []) {
                return;
            }

            $btDevice = BluetoothDevice::where('mac', $mac)->first();
            if ($btDevice === null) {
                return;
            }

            Log::info('BLE relay connected, sending queued writes', ['mac' => $mac, 'pending' => count($pending)]);

            // This runs in the long-lived MQTT listener, whose publisher
            // connection may have sat idle long enough for the broker to drop
            // it without the client noticing - a QoS 0 publish on it is then
            // lost without an error. Start a fresh one.
            MQTT::disconnect('publisher');
            foreach ($pending as $item) {
                $proxy->btWrite($btDevice, $item['data'], (int) $item['cmd']);
            }
        } catch (Throwable $e) {
            Log::error('BLE relay flush failed', ['mac' => $mac, 'error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
        }
    }

    private static function key(string $mac, string $suffix): string
    {
        return "ble-relay:{$mac}:{$suffix}";
    }
}
