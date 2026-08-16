<?php

namespace App\Http\Resources;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
                ->whereNotNull('images')
                ->get()
                ->reject(fn (Pet $pet) => empty($pet->images))
                ->map(fn (Pet $pet) => [
                    'id' => $pet->id,
                    'color' => $pet->color ?? '#808080',
                    'discern' => collect($pet->images)
                        ->values()
                        ->map(fn (string $path, int $index) => [
                            // Unique per image, but still traceable back to
                            // the pet: 6 digits total, pet id first, then a
                            // zero-padded ascending number (1, 2, 3, ...)
                            // filling the rest.
                            'id' => (int) ($pet->id . str_pad(
                                (string) ($index + 1),
                                max(1, 6 - strlen((string) $pet->id)),
                                '0',
                                STR_PAD_LEFT
                            )),
                            'url' => $endpoint . Storage::disk('public')->url($path),
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
