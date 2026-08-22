<?php

namespace App\Models;

use Exception;
use App\Petkit\Devices\PuraMax\Device as PuraMaxDevice;
use App\Petkit\Devices\PuraMax\UI as PuraMaxUI;
use App\Petkit\Devices\PuraMax\Configuration as PuraMaxConfiguration;
use App\Petkit\Devices\FreshElement3\Device as FreshElement3Device;
use App\Petkit\Devices\FreshElement3\UI as FreshElement3UI;
use App\Petkit\Devices\FreshElement3\Configuration as FreshElement3Configuration;
use App\Petkit\Devices\FreshElementSolo\Device as FreshElementSoloDevice;
use App\Petkit\Devices\FreshElementSolo\UI as FreshElementSoloUI;
use App\Petkit\Devices\FreshElementSolo\Configuration as FreshElementSoloConfiguration;
use App\Petkit\Devices\YumshareSolo\Device as YumshareSoloDevice;
use App\Petkit\Devices\YumshareSolo\UI as YumshareSoloUI;
use App\Petkit\Devices\YumshareSolo\Configuration as YumshareSoloConfiguration;
use App\Petkit\Devices\YumshareDual\Device as YumshareDualDevice;
use App\Petkit\Devices\YumshareDual\UI as YumshareDualUI;
use App\Petkit\Devices\YumshareDual\Configuration as YumshareDualConfiguration;
use App\Petkit\Devices\PurobotCrystal\Device as PurobotCrystalDevice;
use App\Petkit\Devices\PurobotCrystal\UI as PurobotCrystalUI;
use App\Petkit\Devices\PurobotCrystal\Configuration as PurobotCrystalConfiguration;
use App\Petkit\Devices\EversweetUltra\Device as EversweetUltraDevice;
use App\Petkit\Devices\EversweetUltra\UI as EversweetUltraUI;
use App\Petkit\Devices\EversweetUltra\Configuration as EversweetUltraConfiguration;
use App\Helpers\HomeassistantHelper;
use App\Helpers\JsonHelper;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\SetProperty;
use App\Petkit\DeviceDefinition;
use App\Petkit\Interfaces\HasCamera;

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
        self::creating(function ($device) {
            if (empty($device->name)) {
                $device->name = $device->serial_number;
            }
        });

        self::updated(function ($device) {

            try {
                if (isset($device->getChanges()['configuration'])) {
                    $device->definition()->propertyChange($device);
                }
            } catch (Exception $e) {
                // propertyChange() runs the whole config->wire pipeline
                // (schedule normalization, MQTT publish via
                // SetProperty::dispatchSync()) synchronously right here.
                // This used to be a bare empty catch, so ANY exception in
                // that pipeline - a malformed schedule shape, an MQTT
                // connection failure, anything - silently dropped the
                // device push with zero trace anywhere. Log it so failures
                // are actually diagnosable instead of just "the device
                // didn't update and nothing explains why".
                Log::error('propertyChange failed for device ' . $device->id, [
                    'exception' => $e,
                ]);
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
        'ota_state', 'ota_available', 'available_version', 'name', 'debug_mode', 'device_type', 'firmware', 'mac', 'timezone', 'locale', 'petkit_id', 'serial_number', 'bt_mac', 'ap_mac', 'chip_id', 'mqtt_subdomain', 'last_heartbeat', 'working_state', 'error', 'mqtt_connected', 'configuration', 'secret', 'link_with'
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
            't4' => new PuraMaxDevice($this),
            'd3' => new FreshElement3Device($this),
            'd4' => new FreshElementSoloDevice($this),
            'd4h' => new YumshareSoloDevice($this),
            'd4sh' => new YumshareDualDevice($this),
            't7' => new PurobotCrystalDevice($this),
            'w7h' => new EversweetUltraDevice($this),
        };
    }

    public function configuration()
    {

        return match ($this->device_type) {
            't4' => PuraMaxConfiguration::fromDevice($this),
            'd3' => FreshElement3Configuration::fromDevice($this),
            'd4' => FreshElementSoloConfiguration::fromDevice($this),
            'd4h' => YumshareSoloConfiguration::fromDevice($this),
            'd4sh' => YumshareDualConfiguration::fromDevice($this),
            't7' => PurobotCrystalConfiguration::fromDevice($this),
            'w7h' => EversweetUltraConfiguration::fromDevice($this),
        };
    }

    public function ui()
    {

        return match ($this->device_type) {
            't4' => new PuraMaxUI($this),
            'd3' => new FreshElement3UI($this),
            'd4' => new FreshElementSoloUI($this),
            'd4h' => new YumshareSoloUI($this),
            'd4sh' => new YumshareDualUI($this),
            't7' => new PurobotCrystalUI($this),
            'w7h' => new EversweetUltraUI($this),
        };
    }

    public function isNextGen(): ?bool
    {
        if ($this->device_type === null) {
            return null;
        }

        return $this->configuration() instanceof HasCamera;
    }

    public function bleLinked()
    {
        return $this->hasMany(BluetoothDevice::class, 'link_with', 'id');
    }
}
