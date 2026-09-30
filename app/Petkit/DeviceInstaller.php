<?php

namespace App\Petkit;

use Generator;
use RuntimeException;

/**
 * DeviceInstaller
 *
 * Coordinates a telnet connection to a PetKit device and runs the
 * universal LocalKit installer on the device. Responsibilities:
 *
 * - Build a safe, server-side-only shell command that downloads and runs
 *   the upstream installer (`/scripts/install`) on the device so the
 *   device-side installer can fetch model-specific installers itself.
 * - Use the existing TelnetService to authenticate and stream output back
 *   to the caller in near real time.
 *
 * The class intentionally does not attempt to detect the PetKit model;
 * the device-side universal installer is responsible for
 * dispatching to the correct model-specific installer.
 */
class DeviceInstaller
{
    public function getInstallerUrl(): string
    {
        $localIp = trim((string) config('petkit.local_ip'));

        if (empty($localIp) || $localIp === '127.0.0.1' || str_starts_with($localIp, '127.') || $localIp === 'localhost') {
            throw new RuntimeException('PETKIT_LOCAL_IP is not configured in .env');
        }

        if (str_starts_with($localIp, 'http://') || str_starts_with($localIp, 'https://')) {
            $base = rtrim($localIp, '/');
        } else {
            if (!str_contains($localIp, ':')) {
                $port = request() ? request()->getPort() : null;
                if ($port && !in_array($port, [80, 443])) {
                    $localIp .= ':' . $port;
                }
            }
            $base = 'http://' . $localIp;
        }

        return $base . '/scripts/install';
    }

    public function installStream(string $ip): Generator
    {
        // Validate target IP (Telnet target)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new RuntimeException('Invalid IPv4 address');
        }

        $installerUrl = $this->getInstallerUrl();

        // Download the universal installer and pipe directly to sh
        $command = "wget -qO- '{$installerUrl}' | sh; exit";

        // Use the container-resolved TelnetService for streaming; pass the
        // supplied $ip only as the Telnet target.
        $service = app(TelnetService::class);
        yield from $service->runCommandStream($ip, $command);
    }
}
