<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Access-log style record of every incoming HTTP request to storage/logs/http.log,
 * unconditionally - unlike LogDeviceHttpRequests (full request/response dump,
 * but only for devices with debug_mode on). Registered as global middleware
 * so it also covers routes outside the api group (oci/*, poll/*).
 */
class LogHttpRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        Log::channel('http')->debug(sprintf(
            '%s %s -> %d',
            $request->method(),
            '/' . $request->path(),
            $response->getStatusCode(),
        ), [
            'ip' => $request->ip(),
            'content_length' => $request->header('Content-Length'),
        ]);

        return $response;
    }
}
