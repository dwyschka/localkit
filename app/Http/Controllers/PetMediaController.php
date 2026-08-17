<?php

namespace App\Http\Controllers;

use App\Models\PetImage;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a pet's discern reference photo by its PetImage id (see
 * DevDiscernPicResource) - extensionless on purpose, matching the shape of
 * the real cloud's own discern image URLs (opaque path, no file extension).
 */
class PetMediaController extends Controller
{
    public function __invoke(int $id)
    {
        $image = PetImage::findOrFail($id);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($image->path), 404);

        return $disk->response($image->path, null, [
            'Content-Type' => $disk->mimeType($image->path) ?: 'image/jpeg',
        ]);
    }
}
