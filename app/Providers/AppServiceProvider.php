<?php

namespace App\Providers;

use Exception;
use App\Localkit\OTA;
use App\Management\Rtsp;
use App\Management\S6;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every process (web workers, localkit:listen, localkit:homeassistant)
        // would otherwise publish with the same client id, and each connect
        // makes the broker drop another process's connection, losing its next
        // publish. The suffix is stripped first because config:cache runs this
        // too. Session and auto-reconnect settings stay as configured: the
        // listener holds its publisher open for hours and needs auto-reconnect.
        foreach (['publisher', 'homeassistant-publisher'] as $connection) {
            $clientId = preg_replace('/_\d+$/', '', config("mqtt-client.connections.$connection.client_id"));
            config([
                "mqtt-client.connections.$connection.client_id" => $clientId . '_' . getmypid(),
            ]);
        }

        if(config('petkit.bypass_auth')) {
            try {
                $user = User::all()->first();

                if(is_null($user)) {
                    throw new Exception('You need to create a User');
                }

                Auth::loginUsingId($user->id);
            } catch (Exception $e) {}
        }
        Arr::macro('mergeRecursiveDistinct', function (array $array1, array $array2) {
            $merged = $array1;

            foreach ($array2 as $key => $value) {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                    $merged[$key] = Arr::mergeRecursiveDistinct($merged[$key], $value);
                } else {
                    $merged[$key] = $value;
                }
            }

            return $merged;
        });

        $this->app->bind(S6::class, function () {
            return new S6();
        });

        $this->app->bind(Rtsp::class, function () {
            return new Rtsp();
        });

        $this->app->bind(OTA::class, fn() => new OTA());

    }
}
