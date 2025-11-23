<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class Service extends Model
{
    use Sushi;

    protected array $readOnly = ['php-fpm', 'nginx'];
    protected array $hiddenEntries = ['init'];

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
                'readonly' => in_array($service['name'], $this->readOnly),
                'hidden' => in_array($service['name'], $this->hiddenEntries)

            ];
        })->filter(fn($item) => !$item['hidden'])->sortBy('readonly')->toArray();
    }
}
