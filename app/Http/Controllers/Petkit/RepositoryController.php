<?php

namespace App\Http\Controllers\Petkit;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Response;

class RepositoryController extends Controller
{
    public function __invoke(Request $request, string $path): Response
    {
        $upstream = 'http://tool.localkit.io/repository/';

        $headers = collect($request->headers->all())
            ->except(['host', 'content-length'])
            ->all();

        $upstream_response = Http::withHeaders($headers)
            ->send($request->method(), $upstream . $path, [
                'body' => $request->getContent(),
            ]);

        $response = response($upstream_response->body(), $upstream_response->status())
            ->header('Content-Type', $upstream_response->header('Content-Type') ?? 'application/octet-stream');

        if (str_ends_with($path, '.bin')) {
            $response->header('Content-Disposition', 'attachment; filename="' . basename($path) . '"');
        }

        return $response;
    }
}
