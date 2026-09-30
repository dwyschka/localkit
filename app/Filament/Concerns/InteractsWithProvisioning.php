<?php

namespace App\Filament\Concerns;

use App\Models\Device;
use App\Petkit\TelnetClient;
use App\Petkit\ProvisioningRegistry;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side half of BLE provisioning, for any Livewire component that shows
 * the provisioning form.
 *
 * The Bluetooth work itself happens in the browser
 * (public/js/localkit/provisioning.js) - the container has no radio, and even
 * with one it would have to be in range of the device. All that is needed here
 * are the defaults the form opens with and the answer to "has it turned up
 * yet?" once the browser is done.
 *
 * Used by the Provisioning page and by the "Provision Device" action on the
 * device list, so both offer the same form rather than two that drift apart.
 */
trait InteractsWithProvisioning
{
    /** The URL the device will be told to send its API calls to. */
    public string $provisionServer = '';

    /** Hours east of UTC, as the device stores it. */
    public string $provisionTimezone = '';

    /** IANA zone name, which the device keeps beside the offset and echoes back. */
    public string $provisionZone = '';

    /** Highest device id when the run began - see trackedDevice(). */
    public ?int $knownDeviceId = null;

    /** Bare hex MAC of the device the browser picked over BLE, if it reported one. */
    public string $provisionMac = '';

    /** Unix time the wizard began, so a heartbeat is judged fresh against it. */
    public ?int $provisionStartedAt = null;

    /** The device once it has signed up, so it is tracked by id from then on. */
    public ?int $provisionedDeviceId = null;

    /**
     * Fill in what the form starts with. Call this from the component's
     * mount().
     */
    public function mountProvisioningDefaults(): void
    {
        $this->provisionZone = config('app.timezone') ?: date_default_timezone_get();
        $this->provisionServer = $this->defaultProvisionServer();
        $this->provisionTimezone = (string) $this->detectedProvisionOffset();
        $this->knownDeviceId = Device::max('id');
    }

    /**
     * Where the device should phone home to.
     *
     * PetKit's own value is `http://api.eu-pet.com/6/`, and the device appends
     * its paths to it verbatim - so the trailing `/6/` belongs in the URL. Port
     * 80 because ESP32 models (t4, d3, d4) will not use anything else.
     */
    protected function defaultProvisionServer(): string
    {
        return 'http://' . config('petkit.local_ip', '127.0.0.1') . '/6/';
    }

    /**
     * The network the form opens with, for setups where every device joins the
     * same one (LOCALKIT_PROVISIONING_WIFI_SSID / _PASSWORD).
     *
     * Deliberately not Livewire properties: they are only ever prefilled into
     * the form, and the browser hands them to the device over Bluetooth
     * directly - nothing posts them back, so they have no place in the
     * component's state.
     *
     * @return array{ssid: string, password: string}
     */
    public function provisionWifiDefaults(): array
    {
        return [
            'ssid' => (string) config('localkit.provisioning.default_ssid', ''),
            'password' => (string) config('localkit.provisioning.default_password', ''),
        ];
    }

    /**
     * This host's current UTC offset in hours, rounded to the quarter hour the
     * picker offers.
     */
    protected function detectedProvisionOffset(): float
    {
        $seconds = (new DateTimeZone($this->provisionZone))->getOffset(Carbon::now());

        return round($seconds / 3600 * 4) / 4;
    }

    /**
     * UTC offsets from -12:00 to +14:00, keyed by the value the device stores.
     *
     * Offsets rather than city names on purpose: the device parses a single
     * float out of the provisioning payload and has no DST awareness to go with
     * a zone name, so a city list would promise something it does not do. The
     * quarter-hour step is for Nepal (+05:45), India (+05:30) and Chatham
     * (+12:45).
     *
     * @return array<string, string>
     */
    public function provisionTimezoneOptions(): array
    {
        $options = [];

        for ($quarter = -48; $quarter <= 56; $quarter++) {
            $hours = $quarter / 4;
            $sign = $hours < 0 ? '-' : '+';
            $absolute = abs($hours);

            $options[(string) $hours] = sprintf(
                'UTC%s%02d:%02d',
                $sign,
                floor($absolute),
                round(fmod($absolute, 1) * 60)
            );
        }

        return $options;
    }

    /**
     * Record the MAC of a device the browser just provisioned over BLE, so its
     * sign-up here can be recognised and flagged for telnet.
     *
     * Called from the browser with the MAC out of the device's advertised name.
     * The value is untrusted, so it is normalised to bare hex and length-capped
     * before it is used - a MAC is at most 12 hex characters.
     *
     * Kept twice over, for two different readers: in the registry, which the
     * sign-up consults to flag the device for telnet, and on this component,
     * which is how trackedDevice() recognises the device afterwards.
     */
    public function registerProvisioning(string $mac): void
    {
        $normalized = ProvisioningRegistry::normalize($mac);

        if ($normalized === '' || strlen($normalized) > 12) {
            return;
        }

        $this->provisionMac = $normalized;

        ProvisioningRegistry::remember($mac);
    }

    /**
     * Record the address the device reported over Bluetooth (key 111 of its
     * join report), which is the only reliable source for it during a run.
     *
     * Untrusted, so it has to look like an IP address before it is kept.
     */
    public function recordProvisionedAddress(string $ip): void
    {
        $ip = trim($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
            ProvisioningRegistry::rememberAddress($ip);
        }
    }

    /**
     * Mark the start of a provisioning run.
     *
     * The wizard calls this the moment the operator presses start, before the
     * Bluetooth work. It stamps the clock the heartbeat is later judged against
     * and forgets any device tracked from a previous run, so re-running the
     * wizard does not report the last device as this one's.
     *
     * It also opens the run in the registry, which is what the sign-up falls
     * back to when the advertised name carried no MAC to match against, and
     * records the address the device is being pointed at so `dev_serverinfo`
     * can hand back the same one.
     *
     * Called from the browser the moment a device has been picked over
     * Bluetooth, not when the operator pressed start: the chooser has to open
     * inside the click's own user gesture, so nothing may be awaited before it.
     * The device has had nothing written to it yet at that point, so this is
     * still ahead of anything it could report.
     *
     * The address comes from the browser because that is where it is edited -
     * the field is bound in Alpine, so what the operator typed never reaches
     * this component on its own. It is untrusted for the same reason, and falls
     * back to the panel's own default if it is not a usable http(s) URL. The MAC
     * is the one out of the advertised name, and may be empty when the name
     * carried none.
     */
    public function beginProvisioning(string $server = '', string $mac = ''): void
    {
        $this->provisionStartedAt = time();
        $this->provisionedDeviceId = null;
        $this->provisionMac = '';
        $this->knownDeviceId = Device::max('id');

        // Deliberately not written back to $provisionServer: that property is
        // rendered into the wizard's x-data, and changing it mid-run makes
        // Livewire morph the attribute, which tears down and re-creates the
        // Alpine component - along with the polling loop running inside it.
        ProvisioningRegistry::beginRun($this->sanitizeProvisionServer($server));

        if ($mac !== '') {
            $this->registerProvisioning($mac);
        }
    }

    /**
     * The given address if it is one the device could actually call, else the
     * panel's default. A scheme other than http(s) is rejected rather than
     * rewritten: the browser already forces http before sending, so anything
     * else arriving here is not something to guess at.
     */
    protected function sanitizeProvisionServer(string $server): string
    {
        $server = trim($server);

        if ($server === '' || ! filter_var($server, FILTER_VALIDATE_URL)) {
            return $this->defaultProvisionServer();
        }

        return in_array(parse_url($server, PHP_URL_SCHEME), ['http', 'https'], true)
            ? $server
            : $this->defaultProvisionServer();
    }

    /**
     * Where the wizard is: which of the four things it waits for has happened.
     *
     * Polled by the browser once the Bluetooth handshake is done. The phases run
     * strictly in order and each depends on the one before, so this returns the
     * furthest one reached:
     *
     *   idle      - no run is open, so there is nothing to wait for
     *   device    - waiting for the provisioned device to sign up here
     *   heartbeat - it exists, waiting for its first heartbeat since we started
     *   telnet    - heartbeat seen (so the telnetd command has been sent),
     *               waiting for port 23 to open
     *   done      - telnet is reachable
     *
     * `ip` is the address the telnet check is aimed at, surfaced so the wizard
     * can show it and so a stuck run is diagnosable.
     *
     * @return array{phase: string, device: array{id:int,name:string,serial_number:string}|null, ip: string|null}
     */
    public function provisioningStatus(): array
    {
        // Nothing was ever started here. The browser only polls after its
        // Bluetooth half is done, so this means its call to beginProvisioning()
        // never arrived - and waiting would be waiting for nothing.
        if ($this->provisionStartedAt === null) {
            return ['phase' => 'idle', 'device' => null, 'ip' => null];
        }

        $device = $this->trackedDevice();

        if (! $device) {
            return ['phase' => 'device', 'device' => null, 'ip' => null];
        }

        $this->provisionedDeviceId = $device->id;

        $summary = [
            'id' => $device->id,
            'name' => $device->name ?? $device->serial_number,
            'serial_number' => $device->serial_number,
            // Whether this run is what put the device in Localkit, or whether it
            // was already here. Decides how the wizard words the offer to delete
            // it after a failure - throwing away a device with a history behind
            // it is a different proposition from undoing a fresh one.
            'created_in_run' => $this->knownDeviceId === null || $device->id > $this->knownDeviceId,
        ];

        // A heartbeat that predates the wizard is a leftover from an earlier
        // life of the same device, not proof this provisioning reached it.
        $heartbeatSeen = $device->last_heartbeat !== null
            && $this->provisionStartedAt !== null
            && (int) $device->last_heartbeat >= $this->provisionStartedAt;

        if (! $heartbeatSeen) {
            return ['phase' => 'heartbeat', 'device' => $summary, 'ip' => null];
        }

        $ip = $this->telnetTargetIp($device);

        if ($ip !== null && $this->telnetReachable($ip)) {
            $this->finishProvisioning($device);

            return ['phase' => 'done', 'device' => $summary, 'ip' => $ip];
        }

        return ['phase' => 'telnet', 'device' => $summary, 'ip' => $ip];
    }

    /**
     * The install steps the wizard offers once telnet is up.
     *
     * Configured rather than built in (localkit.provisioning.install_steps):
     * what belongs on a device is a property of the installation, not of this
     * panel, and an empty list means the wizard simply makes no offer.
     *
     * @return array<int, array{name: string, description: string, command: string}>
     */
    public function provisioningInstallSteps(): array
    {
        $steps = [];

        foreach ((array) config('localkit.provisioning.install_steps', []) as $step) {
            if (empty($step['command'])) {
                continue;
            }

            $steps[] = [
                'name' => (string) ($step['name'] ?? 'Install step'),
                'description' => (string) ($step['description'] ?? ''),
                'command' => (string) $step['command'],
            ];
        }

        return $steps;
    }

    /**
     * Run one install step on the device and hand back what it said.
     *
     * One step per call, so the wizard can print each one's output as it
     * happens rather than going quiet for however long the whole list takes.
     * Each call opens its own telnet session: Livewire calls are separate
     * requests with nothing shared between them, and a login costs far less
     * than the alternative of holding a socket open across them.
     *
     * The index comes from the browser and is checked against the configured
     * list, so it can only ever name a command that is already configured here.
     *
     * @return array{ok: bool, name: string, command: string, output: string, error: string|null, last: bool}
     */
    public function runProvisioningInstallStep(int $index): array
    {
        $steps = $this->provisioningInstallSteps();
        $step = $steps[$index] ?? null;

        $result = [
            'ok' => false,
            'name' => $step['name'] ?? '',
            'command' => $step['command'] ?? '',
            'output' => '',
            'error' => null,
            'last' => $index >= count($steps) - 1,
        ];

        if (! $step) {
            $result['error'] = 'No such install step.';

            return $result;
        }

        $run = $this->execOnDevice($step['command']);

        $result['ok'] = $run['ok'];
        $result['output'] = $run['output'];
        $result['error'] = $run['error'];

        return $result;
    }

    /**
     * Run one command the operator typed, on the device this run is about.
     *
     * The wizard's console is the only place a device's shell is reachable from
     * while it is being provisioned - MQTT is deliberately unreachable and the
     * device page has nothing for it either - so a stuck device would otherwise
     * mean going and finding a telnet client. Anything that can be typed here
     * runs as root on the device, exactly as it would over telnet by hand.
     *
     * A script rather than a single command is handed over as a script: written
     * to the device and run from there, so `if`, `for` and everything else that
     * spans lines behaves the way it reads.
     *
     * @return array{ok: bool, command: string, output: string, error: string|null}
     */
    public function runProvisioningCommand(string $command): array
    {
        $command = trim($command);

        if ($command === '') {
            return ['ok' => false, 'command' => '', 'output' => '', 'error' => 'Nothing to run.'];
        }

        $run = $this->execOnDevice($this->asShellCommand($command));

        return [
            'ok' => $run['ok'],
            'command' => $command,
            'output' => $run['output'],
            'error' => $run['error'],
        ];
    }

    /**
     * A multi-line script as one command: written out with printf and run from
     * a file. Telnet carries one line at a time, and feeding a script's lines
     * in one by one would run each in isolation - a `for` loop would be three
     * separate syntax errors rather than a loop.
     *
     * Every line is single-quoted, with embedded quotes closed and reopened the
     * usual way, so the shell on the far end sees the script exactly as typed.
     */
    protected function asShellCommand(string $input): string
    {
        $lines = preg_split('/\R/', $input) ?: [];

        if (count($lines) < 2) {
            return $input;
        }

        $quoted = array_map(
            fn (string $line) => "'" . str_replace("'", "'\\''", $line) . "'",
            $lines
        );

        return "printf '%s\\n' " . implode(' ', $quoted)
            . ' > /tmp/localkit-run.sh && sh /tmp/localkit-run.sh';
    }

    /**
     * Connect to the device this run is about and run one command on it.
     *
     * A session per command: Livewire calls are separate requests with nothing
     * shared between them, and a login costs far less than trying to hold a
     * socket open across them.
     *
     * @return array{ok: bool, output: string, error: string|null}
     */
    protected function execOnDevice(string $command): array
    {
        $device = $this->trackedDevice();
        $ip = $device ? $this->telnetTargetIp($device) : null;
        $username = config('petkit.telnet_username');
        $password = config('petkit.telnet_password');

        if (! $ip) {
            return ['ok' => false, 'output' => '', 'error' => 'The device address is not known, so there is nowhere to run this.'];
        }

        if (empty($username) || empty($password)) {
            return ['ok' => false, 'output' => '', 'error' => 'Telnet credentials are not configured (DEVICE_TELNET_USERNAME / DEVICE_TELNET_PASSWORD).'];
        }

        $telnet = new TelnetClient($ip);

        try {
            $telnet->login($username, $password);

            return [
                'ok' => true,
                'output' => $this->tidyTelnetOutput($telnet->exec($command), $command),
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Telnet command on a provisioned device failed', [
                'device_id' => $device?->id,
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'output' => '', 'error' => $e->getMessage()];
        } finally {
            $telnet->close();
        }
    }

    /**
     * What the shell said, without the parts the shell says to itself: the
     * echoed command and the prompt it returns to.
     */
    protected function tidyTelnetOutput(string $output, string $command): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $output));

        // The shell echoes the command back before it runs it.
        if (isset($lines[0]) && trim($lines[0]) === trim($command)) {
            array_shift($lines);
        }

        /*
         * TelnetClient::exec() reads until the prompt the shell returns to, so
         * whatever follows the last newline is that prompt and not output. Its
         * shape varies ("[root@localhost /]#", "~ #", "$"), which is why it is
         * recognised by position rather than by matching the prompt itself -
         * the one case that needs a pattern is a prompt with no newline before
         * it, where only a bracketed one can be told from the output it is
         * stuck to.
         */
        $last = array_key_last($lines);

        if ($last !== null && preg_match('/[#$]\s*$/', $lines[$last])) {
            if ($last > 0) {
                unset($lines[$last]);
            } else {
                $lines[$last] = preg_replace('/\[[^\]\n]{0,64}\]\s*[#$]\s*$/', '', $lines[$last]);
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Throw away the device this run produced.
     *
     * Offered when a run fails part way: a half-provisioned device left behind
     * is mostly in the way, and an operator who is about to try again would
     * rather start from nothing. Never automatic - the wizard asks first, and
     * this only ever touches the device the run is about.
     *
     * @return array{deleted: bool, name: string|null}
     */
    public function deleteProvisionedDevice(): array
    {
        $device = $this->trackedDevice();

        if (! $device) {
            return ['deleted' => false, 'name' => null];
        }

        $name = $device->name ?? $device->serial_number;
        $id = $device->id;

        $device->delete();

        ProvisioningRegistry::endRun($id);

        $this->provisionedDeviceId = null;
        $this->knownDeviceId = Device::max('id');

        return ['deleted' => true, 'name' => $name];
    }

    /**
     * Close the provisioning out on the device itself.
     *
     * Telnet answering is the finish line: the heartbeat has no more reason to
     * carry the command that started it, and the per-device HTTP log has done
     * its job. Both were only ever meant to last the length of a run, so they
     * come off here rather than waiting for someone to remember the toggles on
     * the device page.
     *
     * Clearing the flag also gives the device back its MQTT host and puts
     * `dev_serverinfo` back on the panel's own address - which is the same one
     * in the usual setup, but not if the run was pointed somewhere else.
     */
    protected function finishProvisioning(Device $device): void
    {
        if (! $device->provisioning && ! $device->debug_mode) {
            return;
        }

        $device->update(['provisioning' => false, 'debug_mode' => false]);
    }

    /**
     * The device this run is provisioning.
     *
     * Four ways of saying "this one", in order of how certain they are:
     *
     *   1. the id, once a previous poll has pinned it;
     *   2. the device the sign-up itself reported - it knew, because its own
     *      sign-up consumed this run's mark;
     *   3. the MAC the browser saw advertised, matched against what the device
     *      reported at sign-up;
     *   4. a device that appeared since the run began.
     *
     * (4) alone waits forever for a device Localkit has seen before: `dev_signup`
     * keys on the serial number, so such a device is updated in place and never
     * gets a new id. (3) covers that only when the advertised name carried a MAC,
     * which is not always - hence (2), which needs neither.
     */
    protected function trackedDevice(): ?Device
    {
        // No run, nothing to track. Without this, a panel sitting open on the
        // wizard reports the next device to sign up as though it had provisioned
        // it - and (4) below cannot tell the difference, because on an empty
        // device table there is no highest id to compare against.
        if ($this->provisionStartedAt === null) {
            return null;
        }

        if ($this->provisionedDeviceId !== null) {
            return Device::find($this->provisionedDeviceId);
        }

        $claimed = ProvisioningRegistry::claimedDevice();

        if ($claimed !== null && $device = Device::find($claimed)) {
            return $device;
        }

        return $this->deviceByProvisionedMac()
            ?? Device::when(
                $this->knownDeviceId !== null,
                fn ($query) => $query->where('id', '>', $this->knownDeviceId)
            )->latest('id')->first();
    }

    /**
     * The device reporting the MAC the browser picked over BLE, if it has signed
     * up. A device reports two addresses and the advertised name carries only
     * one of them, so both are compared - the same pairing the registry makes.
     *
     * Matched in PHP rather than in the query: the stored formatting varies by
     * firmware ("a4c138..." or "a4:c1:38:..."), and normalising both sides is
     * clearer than a SQL expression that has to strip separators. The device
     * table is small enough that reading the two columns costs nothing.
     */
    protected function deviceByProvisionedMac(): ?Device
    {
        if ($this->provisionMac === '') {
            return null;
        }

        $id = Device::query()
            ->get(['id', 'mac', 'bt_mac'])
            ->first(fn (Device $device) => in_array($this->provisionMac, [
                ProvisioningRegistry::normalize($device->mac),
                ProvisioningRegistry::normalize($device->bt_mac),
            ], true))
            ?->id;

        return $id === null ? null : Device::find($id);
    }

    /**
     * The address to try telnet on, best source first.
     *
     *   1. what the device said over Bluetooth, in the join report it sends
     *      while still being provisioned;
     *   2. what it has reported to Localkit for itself;
     *   3. the source address of its last heartbeat.
     *
     * (3) is a last resort and is often wrong: behind Docker's published ports
     * a request appears to come from the gateway, so it yields an address like
     * 192.168.65.1 that belongs to Docker rather than to the device. (2) is
     * reliable but arrives over MQTT, which is deliberately unreachable for a
     * device that is still being provisioned - hence (1), which is the only
     * source available in the one window the wizard cares about.
     */
    protected function telnetTargetIp(Device $device): ?string
    {
        if ($announced = ProvisioningRegistry::address()) {
            return $announced;
        }

        try {
            $reported = $device->configuration()->ipAddress ?? null;
        } catch (Throwable $e) {
            $reported = null;
        }

        if (! empty($reported)) {
            return $reported;
        }

        $cached = cache()->get("provisioning:hb-ip:{$device->id}");

        return empty($cached) ? null : $cached;
    }

    /**
     * Whether something is listening on the device's telnet port. A short
     * connect is enough - telnetd answers as soon as it is up, and the wizard
     * only needs to know that it is.
     */
    protected function telnetReachable(string $ip): bool
    {
        $connection = @fsockopen($ip, 23, $errno, $errstr, 1.0);

        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
