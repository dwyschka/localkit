<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

/**
 * Proxies the OTA repository for devices without internet access.
 *
 * Firmware (.bin) files are cached locally and served with an explicit
 * Content-Length and HTTP Range support: the ESP32 OTA client aborts chunked
 * responses and resumes interrupted downloads with "Range: bytes=N-", which
 * the upstream repository does not honour (it returns 200 + the full file,
 * corrupting the image -> otafail-13).
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

            $body = $disk->get($cacheKey);
            $size = strlen($body);
            $status = 200;
            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
                'Accept-Ranges' => 'bytes',
            ];

            if (preg_match('/^bytes=(\d*)-(\d*)$/', (string) $request->header('Range'), $m) && ($m[1] !== '' || $m[2] !== '')) {
                if ($m[1] === '') {            // suffix range: last N bytes
                    $start = max(0, $size - (int) $m[2]);
                    $end = $size - 1;
                } else {
                    $start = (int) $m[1];
                    $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
                }
                if ($start >= $size || $start > $end) {
                    return response('', 416, ['Content-Range' => "bytes */$size"]);
                }
                $body = substr($body, $start, $end - $start + 1);
                $status = 206;
                $headers['Content-Range'] = "bytes $start-$end/$size";
            }

            $headers['Content-Length'] = (string) strlen($body);
            return response($body, $status, $headers);
        }

        // Non-firmware paths: plain pass-through.
        $headers = collect($request->headers->all())
            ->except(['host', 'content-length', 'range'])
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
