<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class Service extends Model
{
    use Sushi;

    protected $schema = [
        'name' => 'string',
        'statename' => 'string',
        'readonly' => 'boolean',
    ];

    public function getRows()
    {
        $services = app(\App\Management\Supervisor::class)->allServices();

        return $services->map(function ($service) {
            return [
                ...$service,
                'readonly' => in_array($service['name'], ['php-fpm', 'nginx', 'init'])
            ];
        })->sortBy('readonly')->toArray();
    }
}
