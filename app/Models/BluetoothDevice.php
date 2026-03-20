<?php

namespace App\Models;

use App\Petkit\BluetoothDevices\K3;
use App\Petkit\BluetoothDevices\W5;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;

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

            if($device->isDirty('link_with')) {
                $oldLinkedDevice = Device::find($device->getOriginal('link_with'));
                if(!is_null($oldLinkedDevice)) {
                    $oldLinkedDevice->definition()->unlink($device);
                }
                $device->linkWith->definition()->link($device);
            }

        });

        self::creating(function (self $device) {
            $device->configuration = $device->configuration()->toArray();
        });
    }

    public function configuration()
    {

        return match ($this->type) {
            'k3' => K3\Configuration::fromDevice($this),
            'w5' => W5\Configuration::fromDevice($this)
        };
    }

    public function ui()
    {
        if($this->type === null) {
            return null;
        }
        return match ($this->type) {
            'k3' => new K3\UI($this),
            'w5' => new W5\UI($this)
        };
    }

    public function device()
    {

        return match ($this->type) {
            'k3' => new K3\Device($this),
            'w5' => new W5\Device($this)
        };
    }

    public function bluetoothDeviceType(): int {
        return match($this->type) {
            'w5' => 14,
            default => throw new \Exception('No valid device')
        };
    }

    public function linkWith(): BelongsTo {
        return $this->belongsTo(Device::class, 'link_with', 'id');
    }
}
