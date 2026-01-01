<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Sushi\Sushi;
use function PHPSTORM_META\map;

class Service extends Model
{
    use Sushi;

    protected $services = [
        'localkit-homeassistant',
        'localkit-go2rtc',
        'localkit-listen'
    ];
    protected $schema = [
        'name' => 'string',
        'statename' => 'string',
        'readonly' => 'boolean',
    ];

    public function getRows()
    {
        $runningServices = app(\App\Management\S6::class)->listRunningServices();
        $services = collect($this->services)->map(function ($service) use($runningServices) {
            return [
                'name' => $service,
                'statename' => in_array($service, $runningServices) ? 'RUNNING' : 'STOPPED'
            ];
        })->values()->sortBy('statename')->toArray();

        return $services;
    }
}
