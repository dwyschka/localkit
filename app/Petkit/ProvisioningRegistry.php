<?php

namespace App\Petkit;

use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Remembers, for a short while, that a device was just set up over BLE from the
 * panel - so that when it signs up we can tell it apart from every other device
 * and flag it for telnet.
 *
 * Two marks, because there are two ways to recognise the device and only one of
 * them is reliable:
 *
 * The MAC is the precise one. During BLE provisioning the browser knows the
 * device's advertised name ("Petkit_D4SH_a4c138a66d88"), which carries a MAC; at
 * sign-up the device reports its WiFi `mac` and its Bluetooth `bt_mac`. Which of
 * the two the advertised name held is not worth guessing, so a pending MAC is
 * matched against both.
 *
 * The run is the one that actually holds. An advertised name does not always
 * carry a MAC - it is truncated to fit the 31-byte advertising packet, and the
 * segment count varies by model - so the precise path silently produces nothing
 * for some devices. A device signing up while the operator is standing in front
 * of the wizard is the device being provisioned, whatever it calls itself, so
 * the run itself is marked too and used when no MAC matched.
 *
 * Cache, not a table: this is a note to self that is only useful for the couple
 * of minutes between provisioning and the device coming online, and the
 * configured cache store (database) already outlives a single request.
 */
class ProvisioningRegistry
{
    /** How long a provisioned device has to come online before the mark lapses. */
    private const TTL_MINUTES = 30;

    /** The mark for "a provisioning run is under way", which has no MAC in it. */
    private const RUN_KEY = 'provisioning:run';

    /** Which device the open run turned out to be about, once it has signed up. */
    private const CLAIMED_KEY = 'provisioning:claimed';

    /** The address the device reported over Bluetooth during the open run. */
    private const ADDRESS_KEY = 'provisioning:address';

    /**
     * Strip a MAC down to bare lowercase hex, so "A4:C1:38" , "a4-c1-38" and
     * "a4c138" all compare equal. Returns '' for anything with no hex in it.
     */
    public static function normalize(?string $mac): string
    {
        return Str::lower(preg_replace('/[^0-9a-fA-F]/', '', (string) $mac));
    }

    /**
     * Note that a provisioning run has started, and the server address the
     * operator gave it. Set before the Bluetooth work, when the device is not
     * known yet - so it says nothing about which device, only that one is being
     * set up right now and where it is being pointed.
     */
    public static function beginRun(string $server = ''): void
    {
        Cache::put(self::RUN_KEY, $server, now()->addMinutes(self::TTL_MINUTES));

        // Whatever the last run turned out to be about, this one has not found
        // its device yet.
        Cache::forget(self::CLAIMED_KEY);
        Cache::forget(self::ADDRESS_KEY);
    }

    /**
     * Note which device the run turned out to be about. Written by the sign-up,
     * the one moment where the answer is certain - it is the device whose
     * sign-up consumed the run's mark.
     *
     * The wizard reads this rather than working the device out again from its
     * MAC or from its id being new, neither of which holds for a device Localkit
     * has seen before.
     */
    public static function rememberDevice(int $deviceId): void
    {
        Cache::put(self::CLAIMED_KEY, $deviceId, now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * The address the device says it got, straight out of its BLE join report.
     *
     * Kept against the run rather than against a device, because it arrives
     * before the device has signed up and so before there is a device to key it
     * to. The run only ever has one device.
     */
    public static function rememberAddress(string $ip): void
    {
        Cache::put(self::ADDRESS_KEY, $ip, now()->addMinutes(self::TTL_MINUTES));
    }

    /** The address the device reported over Bluetooth, if it reported one. */
    public static function address(): ?string
    {
        $ip = Cache::get(self::ADDRESS_KEY);

        return empty($ip) ? null : $ip;
    }

    /** The device this run is about, if it has signed up yet. */
    public static function claimedDevice(): ?int
    {
        $id = Cache::get(self::CLAIMED_KEY);

        return $id === null ? null : (int) $id;
    }

    /** The address the open run is pointing its device at, if a run is open. */
    public static function runServer(): string
    {
        return (string) Cache::get(self::RUN_KEY, '');
    }

    /**
     * Keep the address a device was provisioned with, so `dev_serverinfo` can
     * hand back what the operator actually typed rather than the panel's own
     * default. Only needed while the device is being set up, so it lapses on
     * the same clock as everything else here.
     */
    public static function rememberServer(int $deviceId, string $server): void
    {
        Cache::put(self::serverKey($deviceId), $server, now()->addMinutes(self::TTL_MINUTES));
    }

    /** The address this device was provisioned with, if it is still known. */
    public static function serverFor(int $deviceId): ?string
    {
        $server = Cache::get(self::serverKey($deviceId));

        return empty($server) ? null : $server;
    }

    /** Note that the device with this MAC was just provisioned over BLE. */
    public static function remember(string $mac): void
    {
        $normalized = self::normalize($mac);

        if ($normalized === '') {
            return;
        }

        Cache::put(self::key($normalized), true, now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Was this device just provisioned from the panel? Its MAC if one was
     * recorded, otherwise the open run.
     *
     * Consumes whichever mark answered, so a device is flagged once: re-signups
     * later (a firmware update, say) are not silently re-provisioned, and only
     * the first device to turn up in a run is taken to be its device.
     *
     * @param string|null ...$macs the device's mac and bt_mac, in any format
     */
    public static function claim(?string ...$macs): bool
    {
        foreach ($macs as $mac) {
            $normalized = self::normalize($mac);

            if ($normalized !== '' && Cache::pull(self::key($normalized)) !== null) {
                Cache::forget(self::RUN_KEY);

                return true;
            }
        }

        // Presence, not truthiness: the run's value is the server address, and
        // an operator who cleared the field leaves an empty string behind.
        if (! Cache::has(self::RUN_KEY)) {
            return false;
        }

        Cache::forget(self::RUN_KEY);

        return true;
    }

    /**
     * Take this device to be the one the open run is about, if the run has not
     * found its device yet, and set it up for the rest of the run.
     *
     * Called from wherever a device first shows itself - its sign-up, or its
     * first heartbeat. A device Localkit already knows has an id and a secret
     * of its own and can go straight to the heartbeat without signing up again,
     * so the sign-up alone would miss exactly the devices that were provisioned
     * once before.
     *
     * Returns whether this call is what claimed the run.
     */
    public static function adopt(Device $device): bool
    {
        if ($device->provisioning) {
            return false;
        }

        // Read before claiming: claim() consumes the run, and its value is the
        // address the operator gave it.
        $server = self::runServer();

        if (! self::claim($device->mac, $device->bt_mac)) {
            return false;
        }

        self::rememberDevice($device->id);

        if ($server !== '') {
            self::rememberServer($device->id, $server);
        }

        /*
         * Debug logging comes on with the flag: the minutes right after a
         * provisioning are when a device is most likely to go wrong and hardest
         * to ask anything of, and its per-device log
         * (storage/logs/device_<sn>.log) is the only account of what it asked
         * for and what it was told. Both come off again once telnet answers.
         */
        $device->update(['provisioning' => true, 'debug_mode' => true]);

        return true;
    }

    /**
     * Forget everything about the open run. Used when the operator throws away
     * the device it produced, so nothing is left pointing at a row that is gone.
     */
    public static function endRun(?int $deviceId = null): void
    {
        Cache::forget(self::RUN_KEY);
        Cache::forget(self::CLAIMED_KEY);
        Cache::forget(self::ADDRESS_KEY);

        if ($deviceId !== null) {
            Cache::forget(self::serverKey($deviceId));
        }
    }

    private static function key(string $normalized): string
    {
        return "provisioning:pending:{$normalized}";
    }

    private static function serverKey(int $deviceId): string
    {
        return "provisioning:server:{$deviceId}";
    }
}
