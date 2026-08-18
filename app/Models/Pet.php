<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Pet extends Model
{
    protected $fillable = ['name', 'color', 'weight', 'birthdate', 'species', 'gender', 'sterilised'];

    public function images(): HasMany
    {
        return $this->hasMany(PetImage::class)->orderBy('sort_order');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(History::class, 'pet_id')->latest();
    }

    /**
     * Reconciles this pet's images with a plain ordered array of storage
     * paths (the shape the admin form's FileUpload field works with),
     * matching by path so an existing PetImage's id - and thus the discern
     * reference id a device already has cached - survives reordering or
     * unrelated field edits. Only additions/removals get new/deleted rows.
     *
     * @return bool Whether any new photo was added (existing devices need
     *               telling to re-fetch discern references - see
     *               PublishDiscernUpdate).
     */
    public function syncImages(array $paths): bool
    {
        $paths = array_values($paths);
        $existing = $this->images()->get()->keyBy('path');
        $keepPaths = [];
        $added = false;

        foreach ($paths as $index => $path) {
            $image = $existing->get($path);

            if ($image) {
                if ($image->sort_order !== $index) {
                    $image->update(['sort_order' => $index]);
                }
            } else {
                $this->images()->create(['path' => $path, 'sort_order' => $index]);
                $added = true;
            }

            $keepPaths[] = $path;
        }

        $this->images()->whereNotIn('path', $keepPaths)->delete();

        return $added;
    }

    public static function nearestWeight(float $weight)
    {
        $range = .5;

        return Pet::query()->where('weight', '>=', $weight - $range)
            ->where('weight', '<=', $weight + $range)
            ->orderBy(DB::raw('ABS(weight - ' . $weight . ')'))
            ->first();
    }
}
