<?php

namespace Tests\Unit;

use App\Petkit\TelnetService;
use Tests\TestCase;

class TelnetServiceCleanupTest extends TestCase
{
    public function test_exec_closes_socket_on_success()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $state = (object) ['closed' => false];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($state) extends \App\Petkit\TelnetClient {
                private $state;
                public function __construct($state)
                {
                    $this->state = $state;
                }
                public function login(string $username, string $password): void {}
                public function exec(string $command): string
                {
                    return '';
                }
                public function close(): void
                {
                    $this->state->closed = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);
        $svc->exec('127.0.0.1', 'echo hi');

        $this->assertTrue($state->closed);
    }

    public function test_exec_closes_socket_on_exception()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $state = (object) ['closed' => false];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($state) extends \App\Petkit\TelnetClient {
                private $state;
                public function __construct($state)
                {
                    $this->state = $state;
                }
                public function login(string $username, string $password): void {}
                public function exec(string $command): string
                {
                    throw new \RuntimeException('boom');
                }
                public function close(): void
                {
                    $this->state->closed = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);

        $this->expectException(\RuntimeException::class);
        try {
            $svc->exec('127.0.0.1', 'bad');
        } finally {
            $this->assertTrue($state->closed);
        }
    }

    public function test_stream_closes_socket_on_completion()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $state = (object) ['closed' => false];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($state) extends \App\Petkit\TelnetClient {
                private $state;
                public function __construct($state)
                {
                    $this->state = $state;
                }
                public function login(string $username, string $password): void {}
                public function streamExec(string $command, float $maxDuration = 300): \Generator
                {
                    yield "ok\n";
                }
                public function close(): void
                {
                    $this->state->closed = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);

        foreach ($svc->runCommandStream('127.0.0.1', 'echo hi') as $chunk) {
            // consume
        }

        $this->assertTrue($state->closed);
    }

    public function test_stream_closes_socket_on_exception()
    {
        config(['petkit.telnet_username' => 'u', 'petkit.telnet_password' => 'p']);

        $state = (object) ['closed' => false];
        $telnetFactory = function ($host, $port = 23) use ($state) {
            return new class($state) extends \App\Petkit\TelnetClient {
                private $state;
                public function __construct($state)
                {
                    $this->state = $state;
                }
                public function login(string $username, string $password): void {}
                public function streamExec(string $command, float $maxDuration = 300): \Generator
                {
                    throw new \RuntimeException('stream fail');
                }
                public function close(): void
                {
                    $this->state->closed = true;
                }
            };
        };

        $svc = new TelnetService($telnetFactory);

        $this->expectException(\RuntimeException::class);
        try {
            foreach ($svc->runCommandStream('127.0.0.1', 'bad') as $chunk) {
            }
        } finally {
            $this->assertTrue($state->closed);
        }
    }
}
