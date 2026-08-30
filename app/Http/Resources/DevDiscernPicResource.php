<?php

namespace App\Http\Resources;

use App\Models\Pet;
use App\Models\PetImage;
use Illuminate\Http\Request;

class DevDiscernPicResource extends PetkitHttpResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Same base URL the device already reaches every other emulated
        // endpoint on (see DeviceObjectStorage) - the public disk's own URL
        // is root-relative (config('filesystems.disks.public.url')), so it
        // needs this prefix to be a URL the device can actually fetch.
        $endpoint = rtrim((string) config('localkit.storage.endpoint'), '/');

        return [
            'list' => Pet::query()
                ->has('images')
                ->with('images')
                ->get()
                ->map(fn (Pet $pet) => [
                    'id' => $pet->id,
                    'color' => strtoupper($pet->color ?? '#808080'),
                    'discern' => $pet->images
                        ->map(fn (PetImage $image) => [
                            // The PetImage row's own id - stable across
                            // edits/reordering (see Pet::syncImages()) so a
                            // device that's already cached this id keeps
                            // matching the same photo.
                            'id' => $image->id,
                            // The device can't do TLS, so this must be http
                            // regardless of what scheme $endpoint happens to
                            // carry. Extensionless on purpose (PetMediaController),
                            // matching the real cloud's own discern URLs.
                            'url' => preg_replace('/^https:\/\//', 'http://', $endpoint . '/pet/media/' . $image->id),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
