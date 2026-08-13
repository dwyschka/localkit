<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a storage/logs/*.log file straight to the browser.
 *
 * Deliberately a plain route/controller, not a Livewire component action:
 * Livewire's file-download support reads the whole response body into memory
 * and base64-encodes it into the AJAX payload (see
 * livewire/livewire src/Features/SupportFileDownloads), which blows past
 * PHP's memory_limit on anything but small logs. response()->download()
 * here goes through Symfony's BinaryFileResponse instead, which streams the
 * file in chunks.
 */
class LogDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        // basename() strips any directory traversal - the file must resolve
        // to a plain name directly inside storage/logs.
        $path = storage_path('logs/' . basename($file));

        abort_unless(str_ends_with($path, '.log') && is_file($path) && is_readable($path), 404);

        return response()->download($path);
    }
}
