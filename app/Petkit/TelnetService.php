<?php

namespace App\Petkit;

use RuntimeException;
use Generator;
use App\Petkit\Exceptions\TelnetCredentialsNotConfiguredException;
use App\Models\Device;

class TelnetService
{
    public function __construct(private $telnetFactory = null)
    {
        if ($this->telnetFactory === null) {
            $this->telnetFactory = fn($host, $port = 23) => new TelnetClient($host, $port);
        }
    }

    private function validateIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('Invalid IPv4 address');
        }
    }


    /**
     * Execute a single command over telnet (blocking).
     */
    public function exec(string $ip, string $command): void
    {
        $telnet = $this->connect($ip);

        try {
            $telnet->exec($command);
        } finally {
            try {
                $telnet->close();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Connect and return a logged-in TelnetClient for the given IP.
     * Validates credentials and throws a typed exception when missing.
     */
    public function connect(string $ip): TelnetClient
    {
        $this->validateIp($ip);

        $username = config('petkit.telnet_username');
        $password = config('petkit.telnet_password');

        if (empty($username) || empty($password)) {
            throw new TelnetCredentialsNotConfiguredException();
        }

        $telnet = ($this->telnetFactory)($ip, 23);
        $telnet->login($username, $password);

        return $telnet;
    }

    /**
     * Convenience: resolve a Device to an IP address.
     */
    public function getDeviceIp(Device $device): string
    {
        $ip = $device->configuration()->ipAddress ?? null;

        if (empty($ip)) {
            throw new RuntimeException('No IP address known for this device');
        }

        return $ip;
    }

    /**
     * Convenience: exec a command for a Device by resolving its IP.
     */
    public function execDevice(Device $device, string $command): void
    {
        $ip = $this->getDeviceIp($device);
        $this->exec($ip, $command);
    }

    /**
     * Perform a blocking login then stream the output of a command.
     * Yields sanitized chunks for callers to echo.
     */
    public function runCommandStream(string $ip, string $command): Generator
    {
        // Attempt to connect (this validates credentials and will throw
        // a typed exception if they're not configured).
        yield "[LocalKit] Connecting to {$ip}:23...\n";

        $telnet = null;

        try {
            $telnet = $this->connect($ip);

            yield "[LocalKit] Connected.\n";
            yield "[LocalKit] Authentication successful.\n";

            yield "[LocalKit] Starting command...\n";

            foreach ($telnet->streamExec($command) as $chunk) {
                yield OutputSanitizer::sanitize($chunk);
            }
        } catch (RuntimeException $e) {
            // Re-throw runtime exceptions so callers can handle them; for
            // the stream endpoint we'll catch specific credential exceptions.
            throw $e;
        } finally {
            if ($telnet !== null) {
                try {
                    $telnet->close();
                } catch (\Throwable) {
                }
            }
        }
    }
}
