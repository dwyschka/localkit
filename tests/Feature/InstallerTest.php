<?php

namespace Tests\Feature;

use App\Petkit\DeviceInstaller;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    public function test_invalid_ip_rejected()
    {
        $user = User::factory()->create();

        $token = bin2hex(random_bytes(16));
        $resp = $this->withSession(['_token' => $token])->actingAs($user)
            ->postJson(route('installer.run'), ['ip' => 'not-an-ip'], ['X-CSRF-TOKEN' => $token]);

        $resp->assertStatus(422);
    }
    public function test_uses_universal_installer_and_supplied_target_ip()
    {
        config(['petkit.local_ip' => '192.168.1.50']);
        $state = (object) ['ip' => null, 'command' => null];

        $fakeService = new class($state) extends \App\Petkit\TelnetService {
            private $state;

            public function __construct($state)
            {
                $this->state = $state;
            }

            public function runCommandStream(string $ip, string $command): \Generator
            {
                $this->state->ip = $ip;
                $this->state->command = $command;
                yield "ok\n";
            }
        };

        $this->app->instance(\App\Petkit\TelnetService::class, $fakeService);

        $installer = new DeviceInstaller();

        $gen = $installer->installStream('127.0.0.1');
        $out = '';
        foreach ($gen as $chunk) {
            $out .= $chunk;
        }

        $this->assertEquals('127.0.0.1', $state->ip);
        $this->assertStringContainsString('wget -qO-', $state->command);
        $this->assertStringContainsString('http://192.168.1.50/scripts/install', $state->command);
        $this->assertStringContainsString('; exit', $state->command);
    }

    public function test_throws_when_petkit_local_ip_missing_or_loopback()
    {
        $installer = new DeviceInstaller();

        config(['petkit.local_ip' => null]);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PETKIT_LOCAL_IP is not configured in .env');
        iterator_to_array($installer->installStream('192.168.1.100'));
    }

    public function test_throws_when_petkit_local_ip_is_127_0_0_1()
    {
        $installer = new DeviceInstaller();

        config(['petkit.local_ip' => '127.0.0.1']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PETKIT_LOCAL_IP is not configured in .env');
        iterator_to_array($installer->installStream('192.168.1.100'));
    }

    public function test_endpoint_requires_authentication()
    {
        config(['petkit.bypass_auth' => false]);
        \Illuminate\Support\Facades\Auth::forgetGuards();
        \Illuminate\Support\Facades\Auth::logout();
        $resp = $this->postJson('/installer', ['ip' => '127.0.0.1']);
        $this->assertTrue(in_array($resp->getStatusCode(), [401, 419, 302]));
    }
}
