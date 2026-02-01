<?php

namespace App\Models;

use App\Petkit\BluetoothDevices\K3;
use Illuminate\Database\Eloquent\Model;

class BluetoothDevice extends Model
{

    protected $fillable = ['name', 'petkit_id', 'serial_number', 'mac', 'secret', 'type', 'configuration'];

    protected $casts = [
        'configuration' => 'array'
    ];

    public static function booted()
    {
        self::updating(function (self $device) {
            $configuration = $device->configuration();
            $device->configuration = $configuration->toArray();
        });

        self::creating(function (self $device) {
            $device->configuration = $device->configuration()->toArray();
        });
    }

    public function configuration()
    {

        return match ($this->type) {
            'k3' => K3\Configuration::fromDevice($this),
        };
    }

    public function ui()
    {

        return match ($this->type) {
            'k3' => new K3\UI($this)
        };
    }

    public function device()
    {

        return match ($this->type) {
            'k3' => new K3\Device($this)
        };
    }
}
