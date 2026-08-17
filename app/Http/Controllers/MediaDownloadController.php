<?php

namespace App\Http\Controllers;

use App\Filament\Pages\MediaPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file from the object storage disk (see MediaPage) straight to
 * the browser. A plain route, not a Livewire action - see LogDownloadController
 * for why: Livewire's file-download support buffers the whole file into
 * memory and base64-encodes it into the AJAX payload, which doesn't scale to
 * camera clips.
 */
class MediaDownloadController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        $storage = Storage::disk(MediaPage::DISK);

        abort_unless($storage->exists($path), 404);

        return $storage->response($path);
    }
}
