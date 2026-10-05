<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

/**
 * Proxies the OTA repository for devices without internet access.
 *
 * Firmware (.bin) files are cached on disk and delivered in a single full
 * 200 response with an explicit Content-Length - the ESP32 OTA client
 * aborts chunked transfers - plus Content-MD5, ETag and Last-Modified so
 * a retry or resumed download can verify it received the same image.
 */
class RepositoryController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        $upstream = 'http://tool.localkit.io/repository/';

        if (str_ends_with($path, '.bin') && !str_contains($path, '..')) {
            $cacheKey = 'firmware-cache/' . $path;
            $disk = Storage::disk('local');

            if (!$disk->exists($cacheKey)) {
                $upstream_response = Http::timeout(120)->get($upstream . $path);
                if (!$upstream_response->successful()) {
                    return response($upstream_response->body(), $upstream_response->status());
                }
                $disk->put($cacheKey, $upstream_response->body());
            }

            $content = $disk->get($cacheKey);
            $digest = md5($content);

            return response($content, 200)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Length', (string) strlen($content))
                ->header('Content-MD5', base64_encode(hex2bin($digest)))
                ->header('ETag', '"'.$digest.'"')
                ->header('Last-Modified', Carbon::createFromTimestamp($disk->lastModified($cacheKey))->toRfc7231String());
        }

        // Non-firmware paths: plain pass-through.
        $headers = collect($request->headers->all())
            ->except(['host', 'content-length'])
            ->all();

        $upstream_response = Http::withHeaders($headers)
            ->send($request->method(), $upstream . $path, [
                'body' => $request->getContent(),
            ]);

        $body = $upstream_response->body();
        return response($body, $upstream_response->status())
            ->header('Content-Type', $upstream_response->header('Content-Type') ?? 'application/octet-stream')
            ->header('Content-Length', (string) strlen($body));
    }
}