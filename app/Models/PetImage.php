<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PetImage extends Model
{
    protected $fillable = ['pet_id', 'path', 'sort_order'];

    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }
}
