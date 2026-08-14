<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            'path' => __DIR__.'/../routes/api.php',
            'prefix' => '6'
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '6',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
           't6/*',
           'oci/*'
        ]);
        $middleware->appendToGroup('api', \App\Http\Middleware\LogDeviceHttpRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Petkit devices choke on Laravel's HTML error pages (no Accept
        // header to trigger the default JSON-on-expectsJson() behavior), so
        // force an empty JSON body for any error on the device API routes.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('6/*')) {
                $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

                return response()->json([], $status);
            }
        });
    })->create();
