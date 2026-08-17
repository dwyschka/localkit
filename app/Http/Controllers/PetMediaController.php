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
    private const TARGET_SIZE = 224;

    public function __invoke(int $id)
    {
        $image = PetImage::findOrFail($id);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($image->path), 404);

        $contents = $disk->get($image->path);
        [$width, $height] = @getimagesizefromstring($contents) ?: [null, null];

        if ($width === self::TARGET_SIZE && $height === self::TARGET_SIZE) {
            return response($contents)
                ->header('Content-Type', $disk->mimeType($image->path) ?: 'image/jpeg');
        }

        return response($this->resize($contents))
            ->header('Content-Type', 'image/jpeg');
    }

    /**
     * Cover-crops to a square, then scales to the discern model's expected
     * 224x224 input. Uploaded photos are normally already 512x512 (see
     * PetResource's upload-time resize), so this runs on effectively every
     * request - PetResource's target size could be lowered to 224 to make
     * this a no-op for newly uploaded photos, but existing ones would still
     * need it, so the check stays here regardless.
     */
    private function resize(string $contents): string
    {
        $source = imagecreatefromstring($contents);

        if ($source === false) {
            return $contents;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $target = imagecreatetruecolor(self::TARGET_SIZE, self::TARGET_SIZE);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            self::TARGET_SIZE,
            self::TARGET_SIZE,
            $side,
            $side
        );

        ob_start();
        imagejpeg($target, null, 90);

        return ob_get_clean();
    }
}
