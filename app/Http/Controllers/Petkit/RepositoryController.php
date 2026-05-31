<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RepositoryController extends Controller
{
    public function __invoke(Request $request, string $path)
    {
        $upstream = 'http://tool.localkit.io/repository/';

        $response = Http::withHeaders($request->headers->all())
            ->send($request->method(), sprintf('%s%s', $upstream, $path), [
                'body' => $request->getContent(),
            ]);

        $filename = basename($path) ?: 'download.bin';

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?? 'application/octet-stream')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Content-Length', $response->header('Content-Length') ?? strlen($response->body()));
    }
}
