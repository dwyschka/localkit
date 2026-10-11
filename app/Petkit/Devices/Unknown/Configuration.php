<?php

namespace App\Petkit\Devices\Unknown;

use App\DTOs\DeviceConfigurationDTO;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use WendellAdriel\ValidatedDTO\Casting\StringCast;

/**
 * Configuration for a device type Localkit has no implementation for.
 *
 * Deliberately holds nothing device specific: we don't know this firmware's
 * settings keys, and guessing them would mean pushing values the device never
 * asked for. Only the three fields the rest of the app expects every
 * configuration to carry are here - `workingState`/`error`, which
 * Device::updating() writes on every save regardless of type, and
 * `ipAddress`, which is set by hand in the panel (nothing parses state
 * reports for an unknown device) and read by the telnet/stream helpers.
 */
class Configuration extends DeviceConfigurationDTO implements ConfigurationInterface
{
    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:help-circle-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $workingState;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:alert-circle',
        valueTemplate: "{{ 'Ok' if value_json.states.error is none else value_json.states.error }}",
        entityCategory: 'diagnostic'
    )]
    public ?string $error;

    // Panel-only: there is no state parsing for an unknown device, so this is
    // whatever was typed in - never overwritten by the device itself.
    public ?string $ipAddress;

    protected function rules(): array
    {
        return [
            'workingState' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
            'ipAddress' => ['nullable', 'string'],
        ];
    }

    protected function defaults(): array
    {
        return [
            'workingState' => null,
            'error' => null,
            'ipAddress' => null,
        ];
    }

    protected function casts(): array
    {
        return [
            'workingState' => new StringCast(),
            'error' => new StringCast(),
            'ipAddress' => new StringCast(),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
    {
        $config = $device->configuration;

        $data = [
            'workingState' => $config['states']['state'] ?? null,
            'error' => $config['states']['error'] ?? null,
            'ipAddress' => $config['states']['ipAddress'] ?? null,
        ];

        return new self(array_filter($data, fn ($value) => $value !== null));
    }

    public function toArray(): array
    {
        return [
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
                'ipAddress' => $this->ipAddress,
            ],
            // Kept as empty arrays rather than omitted: plenty of callers read
            // configuration['settings'] / ['schedule'] without checking first.
            'settings' => [],
            'schedule' => [],
        ];
    }
}
