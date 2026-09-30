<?php

namespace Tests\Feature;

use App\Petkit\Exceptions\TelnetCredentialsNotConfiguredException;
use App\Petkit\TelnetService;
use App\Models\User;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstallerStreamTest extends TestCase
{
    public function test_stream_returns_credential_messages_when_missing_creds()
    {
        config(['petkit.local_ip' => '192.168.1.50']);
        $user = User::factory()->create();

        // Bind a TelnetService that will throw the credential exception
        $this->app->instance(TelnetService::class, new class extends TelnetService {
            public function runCommandStream(string $ip, string $command): \Generator
            {
                throw new TelnetCredentialsNotConfiguredException();
            }
        });

        // Call the controller action directly and execute the StreamedResponse
        // callback in-process. Use nested buffers so production's ob_flush()
        // only flushes into a test-owned inner buffer instead of PHPUnit's
        // global capture.
        $this->be($user);
        $request = \Illuminate\Http\Request::create(route('installer.run'), 'POST', ['ip' => '127.0.0.1']);

        /** @var StreamedResponse $response */
        $response = app(\App\Http\Controllers\InstallerController::class)->install($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $initialLevel = ob_get_level();
        try {
            ob_start(); // outer capture owned by test
            ob_start(); // inner buffer that production ob_flush() may flush into

            $callback = $response->getCallback();
            $callback();

            $inner = ob_get_clean(); // inner buffer
            $outer = ob_get_clean(); // outer buffer

            $content = $outer . $inner;
        } finally {
            // Clean up any buffers created by this test in case of exceptions.
            while (ob_get_level() > $initialLevel) {
                @ob_end_clean();
            }
        }

        $this->assertStringContainsString('[LocalKit] Telnet credentials not configured.', $content);
        $this->assertStringContainsString('[LocalKit] Set DEVICE_TELNET_USERNAME / DEVICE_TELNET_PASSWORD in .env', $content);
    }

    public function test_installer_endpoint_requires_authentication()
    {
        config(['petkit.bypass_auth' => false]);
        \Illuminate\Support\Facades\Auth::forgetGuards();
        \Illuminate\Support\Facades\Auth::logout();
        $resp = $this->postJson(route('installer.run'), ['ip' => '127.0.0.1']);
        $this->assertTrue(in_array($resp->getStatusCode(), [401, 419, 302]));
    }
}
