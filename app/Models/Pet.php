<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pet extends Model
{
    protected $fillable = ['name', 'images', 'weight', 'birthdate', 'species', 'gender', 'sterilised'];

    protected $casts = [
        'images' => 'array',
    ];

    public static function nearestWeight(float $weight)
    {
        $range = .5;

        return Pet::query()->where('weight', '>=', $weight - $range)
            ->where('weight', '<=', $weight + $range)
            ->orderBy(DB::raw('ABS(weight - ' . $weight . ')'))
            ->first();
    }
}
