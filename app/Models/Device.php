<?php

namespace App\Models;

use App\Helpers\HomeassistantHelper;
use App\Helpers\JsonHelper;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\SetProperty;
use App\Petkit\Devices;
Use App\Petkit\UI;

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
            Log::info('Updated Device', [
                'config' => $device->configuration()
            ]);
            try {
                if (isset($device->getChanges()['configuration'])) {
                    $device->definition()->propertyChange($device);
                }
            } catch (\Exception $e) {


            }

//            if(config('petkit.homeassistant.enabled')) {
                $definition = $device->definition();

                MQTT::connection('homeassistant-publisher')
                    ->publish(HomeassistantHelper::deviceTopic($device), $definition->toHomeassistant(), 0, true);

                if($definition instanceof Snapshot) {
                    $snapshotMessage = $definition->toSnapshot();
                    if(!is_null($snapshotMessage)) {
                        MQTT::connection('homeassistant-publisher')
                            ->publish(HomeassistantHelper::snapshotTopic($device), $snapshotMessage, 0, true);
                    }
                }

                MQTT::connection('homeassistant-publisher')->disconnect();
//            }
        });

        self::updating(function ($device) {

            Log::info('Updating Device', [
                'config' => $device->configuration()
            ]);
            $configuration = $device->configuration();

            $configuration->workingState = $device->working_state;
            $configuration->error = $device->error;

            $device->configuration = $configuration->toArray();

        });
    }
    protected  $casts = [
        'configuration' => 'array'
    ];

    protected $fillable = [
      'ota_state','name','device_type', 'firmware', 'mac', 'timezone', 'locale', 'petkit_id', 'serial_number', 'bt_mac', 'ap_mac', 'chip_id', 'mqtt_subdomain', 'last_heartbeat', 'working_state', 'error', 'mqtt_connected','configuration', 'secret'
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
            'd4' => new Devices\PetkitFreshElementSolo($this),
            'd4h' => new Devices\PetkitYumshareSolo($this),
        };
    }

    public function configuration() {

        return match ($this->device_type) {
            't4' => Devices\Configuration\PetkitPuraMax::fromDevice($this),
            'd4' => Devices\Configuration\PetkitFreshElementSolo::fromDevice($this),
            'd4h' => Devices\Configuration\PetkitYumshareSolo::fromDevice($this),
        };
    }

    public function ui() {

        return match ($this->device_type) {
            't4' => new UI\PetkitPuraMax($this),
            'd4' => new UI\PetkitFreshElementSolo($this),
            'd4h' => new UI\PetkitYumshareSolo($this),
        };
    }

    public function isNextGen() {
        return in_array($this->device_type, ['d4h']);
    }

}
