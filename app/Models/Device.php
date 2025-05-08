<?php

namespace App\Models;

use App\Helpers\HomeassistantHelper;
use App\Helpers\JsonHelper;
use App\Jobs\SetProperty;
use App\Petkit\Devices;
Use App\Petkit\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use PhpMqtt\Client\Facades\MQTT;

class Device extends Model
{

    protected static function booted()
    {
        self::updated(function ($device) {
            try {
                if (isset($device->getChanges()['configuration'])) {
                    $device->definition()->propertyChange($device);
                }
            } catch (\Exception $e) {}

            MQTT::connection('homeassistant-publisher')->publish(HomeassistantHelper::deviceTopic($device), $device->definition()->toHomeassistant(), 0, true);

        });
    }
    protected  $casts = [
        'configuration' => 'array'
    ];

    protected $fillable = [
      'ota_state','name','device_type', 'firmware', 'mac', 'timezone', 'locale', 'petkit_id', 'serial_number', 'bt_mac', 'ap_mac', 'chip_id', 'mqtt_subdomain', 'last_heartbeat', 'working_state', 'error', 'mqtt_connected','configuration'
    ];

    public function histories(): HasMany {
        return $this->hasMany( History::class, 'device_id', 'id' )->orderBy('created_at', 'desc');
    }

    public function deviceName()
    {
        return sprintf('d_%s_%s', $this->device_type, $this->serial_number);
    }

    public function productKey()
    {
        return $this->mqtt_subdomain;
    }


    public function definition() {

        return match ($this->device_type) {
            't4' => new Devices\PetkitPuraMax($this),
            'd4' => new Devices\PetkitEversweetSolo2($this),
        };
    }

    public function ui() {

        return match ($this->device_type) {
            't4' => new UI\PetkitPuraMax($this)
        };
    }

}
