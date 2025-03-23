<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DisableCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if the current route is the restricted one
        foreach ($request->cookies as $cookie => $value) {
            Cookie::queue(Cookie::forget($cookie)); // Remove cookies
        }

        return $response;
    }
}
