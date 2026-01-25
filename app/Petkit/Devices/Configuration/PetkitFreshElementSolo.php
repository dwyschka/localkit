<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\K3ConfigDTO;
use App\DTOs\MultiRangeDTO;
use App\DTOs\RangeDTO;
use App\DTOs\SandFullWeightDTO;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\Device;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;

class PetkitFreshElementSolo extends DeviceConfigurationDTO implements ConfigurationInterface
{

    public int $factor;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:error',
        valueTemplate: "{{ 'Ok' if value_json.states.error is none else value_json.states.error }}",
        entityCategory: 'diagnostic'
    )]
    public ?string $error;

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    public ?string $workingState;

    #[HASwitch(
        technicalName: 'food_warn',
        name: 'Refill alarm',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.foodWarn | string }}',
        commandTemplate: '{"foodWarn":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $foodWarn;

    #[HASwitch(
        technicalName: 'feed_sound',
        name: 'Food dispense prompt tone',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.feedSound }}',
        commandTemplate: '{"feedSound":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $feedSound;

    public bool $multiConfig;

    public RangeDTO $foodWarnRange;

    public RangeDTO $lightRange;

    public bool $shareOpen;

    #[HASwitch(
        technicalName: 'light_mode',
        name: 'Indicator Light',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.lightMode }}',
        commandTemplate: '{"lightMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $lightMode;

    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.manualLock }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $manualLock;

    #[Select(
        technicalName: 'amount',
        name: 'Amount',
        options: [
            '10',
            '15',
            '20',
            '25',
            '30',
            '35',
            '40',
            '45',
            '50'
        ],
        commandTopic: 'setting/set',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.settings.amount }}',
        commandTemplate: ' {"amount": "{{value}}"}',
        entityCategory: 'config'
    )]
    public int $amount;
    public array $schedule;


    #[Button(
        technicalName: 'action_feed',
        name: 'Feed',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "feed"}',
        availabilityTemplate: 'online',
    )]
    private $actionFeed = 1;

    #[Number(
        technicalName: 'desiccant_durability',
        name: 'Desiccant Durability',
        commandTopic: 'setting/set',
        icon: 'mdi:diamond-stone',
        valueTemplate: '{{ value_json.consumables.desiccantDurability }}',
        commandTemplate: '{"desiccantDurability":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        entityCategory: 'config',
        min: 0,
        max: 90,
        step: 1
    )]
    public int $desiccantDurability;


    #[Sensor(
        technicalName: 'durability_in_days',
        name: 'Next Desiccant Change in Days',
        icon: 'mdi:update',
        valueTemplate: '{{ ((value_json.consumables.desiccantNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public int $desiccantNextChange;

    protected function rules(): array
    {
        return [
            'factor' => ['integer', 'min:1', 'max:100'],
            'error' => ['nullable', 'string'],
            'workingState' => ['nullable', 'string'],
            'foodWarn' => ['bool'],
            'feedSound' => ['bool'],
            'multiConfig' => ['bool'],
            'desiccantDurability' => ['integer', 'min:0', 'max:90'],
            'desiccantNextChange' => [ 'integer', 'min:0'],
            'foodWarnRange' => [],
            'lightRange' => [],
            'shareOpen' => ['bool'],
            'lightMode' => ['bool'],
            'manualLock' => ['bool'],
            'amount' => ['integer', 'min:1', 'max:100'],
            'schedule' => ['array']
        ];
    }

    protected function defaults(): array
    {
        return [
            'factor' => 10,
            'error' => null,
            'workingState' => 'IDLE',
            'foodWarn' => true,
            'feedSound' => false,
            'multiConfig' => true,
            'shareOpen' => false,
            'lightMode' => false,
            'manualLock' => false,
            'desiccantDurability' => 30,
            'desiccantNextChange' => 0,
            'amount' => 10,
            'schedule' => [],
            'foodWarnRange' => ['from' => 0, 'till' => 1440],
            'lightRange' => ['from' => 0, 'till' => 1440],
        ];
    }

    protected function casts(): array
    {
        return [
            'factor' => new IntegerCast(),
            'amount' => new IntegerCast(),
            'schedule' => new ArrayCast(),
            'multiConfig' => new BooleanCast(),
            'shareOpen' => new BooleanCast(),
            'lightMode' => new BooleanCast(),
            'manualLock' => new BooleanCast(),
            'foodWarn' => new BooleanCast(),
            'feedSound' => new BooleanCast(),
            'error' => new StringCast(),
            'workingState' => new StringCast(),
            'foodWarnRange' => new DTOCast(RangeDTO::class),
            'lightRange' => new DTOCast(RangeDTO::class),
        ];
    }

    public static function fromDevice(Device $device): self
    {
        $config = $device->configuration;

        $data = [];

        // Load consumables
        $data['desiccantDurability'] = $config['consumables']['desiccantDurability'] ?? null;
        $data['desiccantNextChange'] = $config['consumables']['desiccantNextChange'] ?? null;

        // Load states
        $data['workingState'] = $config['states']['state'] ?? null;
        $data['error'] = $config['states']['error'] ?? null;

        // Load settings
        $data['factor'] = $config['settings']['factor'] ?? null;
        $data['shareOpen'] = $config['settings']['shareOpen'] ?? null;
        $data['multiConfig'] = $config['settings']['multiConfig'] ?? null;
        $data['lightMode'] = $config['settings']['lightMode'] ?? null;
        $data['manualLock'] = $config['settings']['manualLock'] ?? null;
        $data['foodWarn'] = $config['settings']['foodWarn'] ?? null;
        $data['feedSound'] = $config['settings']['feedSound'] ?? null;
        $data['amount'] = $config['settings']['amount'] ?? null;
        $data['lightRange'] = $config['settings']['lightRange'] ?? null;
        $data['foodWarnRange'] = $config['settings']['foodWarnRange'] ?? null;

        // Load schedule
        $data['schedule'] = $config['schedule'] ?? null;

        // Filter out null values to let defaults() handle missing data
        return new self(array_filter($data, fn($value) => $value !== null));
    }

    public function toArray(): array
    {
        return [
            'consumables' => [
                'desiccantDurability' => $this->desiccantDurability,
                'desiccantNextChange' => $this->desiccantNextChange,
            ],
            'states' => [
                'state' => $this->workingState,
                'error' => $this->error,
            ],
            'settings' => [
                'factor' => $this->factor,
                'shareOpen' => $this->shareOpen,
                'multiConfig' => $this->multiConfig,
                'lightMode' => $this->lightMode,
                'manualLock' => $this->manualLock,
                'foodWarn' => $this->foodWarn,
                'feedSound' => $this->feedSound,
                'amount' => $this->amount,
                'lightRange' => $this->lightRange->toArray(),
                'foodWarnRange' => $this->foodWarnRange->toArray(),
            ],
            'schedule' => $this->schedule
        ];
    }

}
