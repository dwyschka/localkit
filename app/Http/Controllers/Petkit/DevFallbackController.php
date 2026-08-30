<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DevFallbackController extends Controller
{
    public function __invoke(string $deviceType, Request $request)
    {
        Log::channel('unmatched')->warning('Unmatched Petkit route', [
            'method'  => $request->method(),
            'url'     => $request->fullUrl(),
            'device'  => $deviceType,
            'headers' => $request->headers->all(),
            // Raw content instead of all() - the body shape on an unmatched
            // route is by definition unverified, and all() can throw on
            // malformed JSON, which would fall through to Laravel's default
            // HTML error page instead of a JSON response.
            'body'    => $request->getContent(),
        ]);

        return response()->json([], 404);
    }
}
