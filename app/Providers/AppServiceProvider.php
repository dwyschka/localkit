<?php

namespace App\Providers;

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

        if(config('petkit.bypass_auth')) {
            try {
                $user = User::all()->first();

                if(is_null($user)) {
                    throw new \Exception('You need to create a User');
                }

                Auth::loginUsingId($user->id);
            } catch (\Exception $e) {}
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

    }
}
