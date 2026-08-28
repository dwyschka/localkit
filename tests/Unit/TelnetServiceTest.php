<?php

namespace Tests\Unit;

use App\Petkit\TelnetService;
use App\Petkit\Exceptions\TelnetCredentialsNotConfiguredException;
use App\Models\Device;
use RuntimeException;
use Tests\TestCase;

class TelnetServiceTest extends TestCase
{
    public function test_connect_throws_when_username_missing()
    {
        config(['petkit.telnet_username' => null, 'petkit.telnet_password' => 'pw']);

        $this->expectException(TelnetCredentialsNotConfiguredException::class);

        $svc = new TelnetService();
        $svc->connect('127.0.0.1');
    }

    public function test_connect_throws_when_password_missing()
    {
        config(['petkit.telnet_username' => 'user', 'petkit.telnet_password' => null]);

        $this->expectException(TelnetCredentialsNotConfiguredException::class);

        $svc = new TelnetService();
        $svc->connect('127.0.0.1');
    }

    public function test_exception_exposes_public_messages_and_no_credentials()
    {
        config(['petkit.telnet_username' => null, 'petkit.telnet_password' => null]);

        try {
            (new TelnetService())->connect('127.0.0.1');
            $this->fail('Expected exception');
        } catch (TelnetCredentialsNotConfiguredException $e) {
            $this->assertEquals('Telnet credentials not configured', $e->getMessage());
            $this->assertEquals('Telnet credentials not configured.', $e->getPublicTitle());
            $this->assertStringContainsString('DEVICE_TELNET_USERNAME', $e->getPublicBody());
        }
    }

    public function test_invalid_ipv4_rejected()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $this->expectException(RuntimeException::class);

        $svc = new TelnetService();
        $svc->connect('not-an-ip');
    }

    public function test_valid_ipv4_calls_login()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $state = (object) ['called' => []];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($host, $port, $state) extends \App\Petkit\TelnetClient {
                private $host;
                private $port;
                private $state;
                public function __construct($h, $p, $state)
                {
                    $this->host = $h;
                    $this->port = $p;
                    $this->state = $state;
                }
                public function login(string $username, string $password): void
                {
                    $this->state->called['login'] = [$username, $password];
                }
                public function close(): void
                {
                    $this->state->called['close'] = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);
        $telnet = $svc->connect('127.0.0.1');

        $this->assertArrayHasKey('login', $state->called);
    }

    public function test_get_device_ip_and_exec_device()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $device = $this->createMock(Device::class);
        $device->method('configuration')->willReturn((object)['ipAddress' => '1.2.3.4']);

        $state = (object) ['captured' => []];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($state) extends \App\Petkit\TelnetClient {
                private $state;
                public function __construct($state)
                {
                    $this->state = $state;
                }
                public function login(string $username, string $password): void
                {
                    $this->state->captured['login'] = [$username, $password];
                }
                public function exec(string $command): string
                {
                    $this->state->captured['exec'] = $command;
                    return '';
                }
                public function close(): void
                {
                    $this->state->captured['close'] = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);

        $this->assertEquals('1.2.3.4', $svc->getDeviceIp($device));

        $svc->execDevice($device, 'reboot');

        $this->assertEquals('reboot', $state->captured['exec']);
        $this->assertTrue($state->captured['close']);
    }

    public function test_get_device_ip_throws_when_no_ip()
    {
        $device = $this->createMock(Device::class);
        $device->method('configuration')->willReturn((object)[]);

        $svc = new TelnetService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No IP address known for this device');
        $svc->getDeviceIp($device);
    }

    public function test_run_command_stream_sanitizes_output()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'supersecret']);

        $telnetFactory = function ($host, $port = 23) {
            return new class extends \App\Petkit\TelnetClient {
                public function __construct()
                {
                    // Test double: deliberately do not create a network connection.
                }

                public function login(string $username, string $password): void {}
                public function streamExec(string $command, float $maxDuration = 300): \Generator
                {
                    yield "doing supersecret stuff\n";
                }
                public function close(): void {}
            };
        };

        $svc = new TelnetService($telnetFactory);

        $out = '';
        foreach ($svc->runCommandStream('127.0.0.1', 'echo hi') as $chunk) {
            $out .= $chunk;
        }

        $this->assertStringNotContainsString('supersecret', $out);
        $this->assertStringContainsString('[REDACTED]', $out);
    }
}
