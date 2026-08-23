<?php

namespace App\Http\Controllers;

use App\Petkit\DeviceInstaller;
use App\Petkit\Exceptions\TelnetCredentialsNotConfiguredException;
use App\Petkit\OutputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class InstallerController extends Controller
{
    public function install(Request $request): StreamedResponse
    {
        $request->validate([
            'ip' => ['required', 'ipv4'],
        ]);
        $ip = $request->input('ip');

        $installerService = new DeviceInstaller();

        $response = new StreamedResponse(function () use ($installerService, $ip) {
            try {
                foreach ($installerService->installStream($ip) as $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();

                    if (function_exists('connection_aborted') && connection_aborted()) {
                        break;
                    }
                }
            } catch (TelnetCredentialsNotConfiguredException $e) {
                echo "[LocalKit] " . $e->getPublicTitle() . "\n";
                echo "[LocalKit] " . $e->getPublicBody() . "\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            } catch (Throwable $e) {
                $message = OutputSanitizer::sanitize($e->getMessage());
                echo "[LocalKit] Error: {$message}\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);

        return $response->setStatusCode(200);
    }
}
