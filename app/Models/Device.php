<?php

namespace App\Models;

use App\Helpers\HomeassistantHelper;
use App\Helpers\JsonHelper;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\SetProperty;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices;
use App\Petkit\Interfaces\HasCamera;
use App\Petkit\UI;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
            } catch (\Exception $e) {


            }

//            if(config('petkit.homeassistant.enabled')) {
            $definition = $device->definition();

            if (config('app.enable.homeassistant')) {

                MQTT::connection('homeassistant-publisher')
                    ->publish(HomeassistantHelper::deviceTopic($device), $definition->toHomeassistant(), 0, true);
            }
            if ($definition instanceof Snapshot) {
                $snapshotMessage = $definition->toSnapshot();
                if (!is_null($snapshotMessage)) {
                    if (config('app.enable.homeassistant')) {
                        MQTT::connection('homeassistant-publisher')
                            ->publish(HomeassistantHelper::snapshotTopic($device), $snapshotMessage, 0, true);
                    }
                }
            }

            if (config('app.enable.homeassistant')) {

                MQTT::connection('homeassistant-publisher')->disconnect();
            }
//            }
        });

        self::updating(function ($device) {

            $configuration = $device->configuration();

            $configuration->workingState = $device->working_state;
            $configuration->error = $device->error;

            $device->configuration = $configuration->toArray();

        });
    }

    protected $casts = [
        'configuration' => 'array',
        'debug_mode' => 'boolean',
    ];

    protected $fillable = [
        'ota_state', 'ota_available', 'available_version', 'name', 'proxy_mode', 'debug_mode', 'device_type', 'firmware', 'mac', 'timezone', 'locale', 'petkit_id', 'serial_number', 'bt_mac', 'ap_mac', 'chip_id', 'mqtt_subdomain', 'last_heartbeat', 'working_state', 'error', 'mqtt_connected', 'configuration', 'secret', 'link_with'
    ];

    public function histories(): HasMany
    {
        return $this->hasMany(History::class, 'device_id', 'id')->orderBy('created_at', 'desc');
    }

    public function deviceName()
    {
        return sprintf('d_%s_%s', $this->device_type, $this->serial_number);
    }

    public function productKey()
    {
        return $this->mqtt_subdomain;
    }


    public function definition(): DeviceDefinition
    {

        return match ($this->device_type) {
            't4' => new Devices\PetkitPuraMax($this),
            'd3' => new Devices\PetkitFreshElement3($this),
            'd4' => new Devices\PetkitFreshElementSolo($this),
            'd4h' => new Devices\PetkitYumshareSolo($this),
            'd4sh' => new Devices\PetkitYumshareDual($this),
            't7' => new Devices\PetkitPurobotCrystal($this),
        };
    }

    public function configuration()
    {

        return match ($this->device_type) {
            't4' => Devices\Configuration\PetkitPuraMax::fromDevice($this),
            'd3' => Devices\Configuration\PetkitFreshElement3::fromDevice($this),
            'd4' => Devices\Configuration\PetkitFreshElementSolo::fromDevice($this),
            'd4h' => Devices\Configuration\PetkitYumshareSolo::fromDevice($this),
            'd4sh' => Devices\Configuration\PetkitYumshareDual::fromDevice($this),
            't7' => Devices\Configuration\PetkitPurobotCrystal::fromDevice($this),
        };
    }

    public function ui()
    {

        return match ($this->device_type) {
            't4' => new UI\PetkitPuraMax($this),
            'd3' => new UI\PetkitFreshElement3($this),
            'd4' => new UI\PetkitFreshElementSolo($this),
            'd4h' => new UI\PetkitYumshareSolo($this),
            'd4sh' => new UI\PetkitYumshareDual($this),
            't7' => new UI\PetkitPurobotCrystal($this),
        };
    }

    public function isNextGen()
    {
        return $this->definition() instanceof HasCamera;
    }

    public function deviceCode(): int
    {
        $definition = $this->definition();

        return $definition instanceof HasCamera ? $definition->deviceCode() : 1;
    }

    public function bleLinked()
    {
        return $this->hasMany(BluetoothDevice::class, 'link_with', 'id');
    }
}
