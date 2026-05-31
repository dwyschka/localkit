<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevFallbackController extends Controller
{
    public function __invoke(string $deviceType, string $fallback, Request $request)
    {
        Log::channel('unmatched')->warning('Unmatched Petkit route', [
            'method'  => $request->method(),
            'url'     => $request->fullUrl(),
            'device'  => $deviceType,
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
        ]);

        return response()->json(['error' => 'not_found'], 404);
    }
}
