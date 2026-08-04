<?php

namespace App\Petkit\Devices\Configuration;

use App\DTOs\DeviceConfigurationDTO;
use App\DTOs\RangeDTO;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\BluetoothDevice;
use App\Models\Device;
use WendellAdriel\ValidatedDTO\Casting\BooleanCast;
use WendellAdriel\ValidatedDTO\Casting\DTOCast;
use WendellAdriel\ValidatedDTO\Casting\IntegerCast;
use WendellAdriel\ValidatedDTO\Casting\StringCast;
use WendellAdriel\ValidatedDTO\Casting\ArrayCast;

class PetkitFreshElement3 extends DeviceConfigurationDTO implements ConfigurationInterface
{

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:alert-circle',
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

    public RangeDTO $lightRange;

    #[HASwitch(
        technicalName: 'sound_enable',
        name: 'Voice for Food Dispensing',
        commandTopic: 'setting/set',
        icon: 'mdi:volume-low',
        valueTemplate: '{{ value_json.settings.soundEnable }}',
        commandTemplate: '{"soundEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $soundEnable;

    #[HASwitch(
        technicalName: 'system_sound_enable',
        name: 'Voice Prompt',
        commandTopic: 'setting/set',
        icon: 'mdi:desktop-classic',
        valueTemplate: '{{ value_json.settings.systemSoundEnable }}',
        commandTemplate: '{"systemSoundEnable":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $systemSoundEnable;

    #[Number(
        technicalName: 'volume',
        name: 'Volume',
        commandTopic: 'setting/set',
        icon: 'mdi:speaker',
        valueTemplate: '{{ value_json.settings.volume }}',
        commandTemplate: '{"volume":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 9,
        step: 1
    )]
    public int $volume;

    public int $selectedSound;

    public string $language;

    #[HASwitch(
        technicalName: 'surplus_control',
        name: 'Surplus Control',
        commandTopic: 'setting/set',
        icon: 'mdi:scale',
        valueTemplate: '{{ value_json.settings.surplusControl }}',
        commandTemplate: '{"surplusControl":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $surplusControl;

    #[Number(
        technicalName: 'surplus',
        name: 'Surplus',
        commandTopic: 'setting/set',
        icon: 'mdi:food-drumstick',
        valueTemplate: '{{ value_json.settings.surplus }}',
        commandTemplate: '{"surplus":{{ value }}}',
        entityCategory: 'config',
        min: 0,
        max: 100,
        step: 10
    )]
    public int $surplus;

    #[HASwitch(
        technicalName: 'disturb_mode',
        name: 'Do Not Disturb',
        commandTopic: 'setting/set',
        icon: 'mdi:sleep',
        valueTemplate: '{{ value_json.settings.disturbMode }}',
        commandTemplate: '{"disturbMode":{{ value }}}',
        payloadOn: true,
        payloadOff: false,
        stateOn: true,
        stateOff: false,
        entityCategory: 'config'
    )]
    public bool $disturbMode;

    public RangeDTO $disturbRange;

    public array $d3SoundFirmware;

    public int $numLimit;

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
        unitOfMeasurement: 'd',
        valueTemplate: '{{ ((value_json.consumables.desiccantNextChange - as_timestamp(now())) / 86400) | round(1) }}',
        entityCategory: 'diagnostic'
    )]
    public int $desiccantNextChange;

    protected function rules(): array
    {
        return [
            'error' => ['nullable', 'string'],
            'workingState' => ['nullable', 'string'],
            'manualLock' => ['bool'],
            'lightMode' => ['bool'],
            'lightRange' => [],
            'soundEnable' => ['bool'],
            'systemSoundEnable' => ['bool'],
            'volume' => ['integer', 'min:0', 'max:9'],
            'selectedSound' => ['integer'],
            'language' => ['string'],
            'surplusControl' => ['bool'],
            'surplus' => ['integer', 'min:0'],
            'disturbMode' => ['bool'],
            'disturbRange' => [],
            'd3SoundFirmware' => ['array'],
            'numLimit' => ['integer', 'min:0'],
            'desiccantDurability' => ['integer', 'min:0', 'max:90'],
            'desiccantNextChange' => ['integer', 'min:0'],
            'amount' => ['integer', 'min:1', 'max:100'],
            'schedule' => ['array']
        ];
    }

    protected function defaults(): array
    {
        return [
            'error' => null,
            'workingState' => 'IDLE',
            'manualLock' => false,
            'lightMode' => true,
            'soundEnable' => false,
            'systemSoundEnable' => true,
            'volume' => 4,
            'selectedSound' => -1,
            'language' => 'en_US',
            'surplusControl' => true,
            'surplus' => 60,
            'disturbMode' => false,
            'd3SoundFirmware' => [],
            'numLimit' => 5,
            'desiccantDurability' => 30,
            'desiccantNextChange' => 0,
            'amount' => 10,
            'schedule' => [],
            'lightRange' => ['from' => 0, 'till' => 1440],
            'disturbRange' => ['from' => 1320, 'till' => 360],
        ];
    }

    protected function casts(): array
    {
        return [
            'amount' => new IntegerCast(),
            'volume' => new IntegerCast(),
            'selectedSound' => new IntegerCast(),
            'surplus' => new IntegerCast(),
            'numLimit' => new IntegerCast(),
            'schedule' => new ArrayCast(),
            'd3SoundFirmware' => new ArrayCast(),
            'manualLock' => new BooleanCast(),
            'lightMode' => new BooleanCast(),
            'soundEnable' => new BooleanCast(),
            'systemSoundEnable' => new BooleanCast(),
            'surplusControl' => new BooleanCast(),
            'disturbMode' => new BooleanCast(),
            'error' => new StringCast(),
            'workingState' => new StringCast(),
            'language' => new StringCast(),
            'lightRange' => new DTOCast(RangeDTO::class),
            'disturbRange' => new DTOCast(RangeDTO::class),
        ];
    }

    public static function fromDevice(Device|BluetoothDevice $device): self
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
        $data['manualLock'] = $config['settings']['manualLock'] ?? null;
        $data['lightMode'] = $config['settings']['lightMode'] ?? null;
        $data['lightRange'] = $config['settings']['lightRange'] ?? null;
        $data['soundEnable'] = $config['settings']['soundEnable'] ?? null;
        $data['systemSoundEnable'] = $config['settings']['systemSoundEnable'] ?? null;
        $data['volume'] = $config['settings']['volume'] ?? null;
        $data['selectedSound'] = $config['settings']['selectedSound'] ?? null;
        $data['language'] = $config['settings']['language'] ?? null;
        $data['surplusControl'] = $config['settings']['surplusControl'] ?? null;
        $data['surplus'] = $config['settings']['surplus'] ?? null;
        $data['disturbMode'] = $config['settings']['disturbMode'] ?? null;
        $data['disturbRange'] = $config['settings']['disturbRange'] ?? null;
        $data['d3SoundFirmware'] = $config['settings']['d3SoundFirmware'] ?? null;
        $data['numLimit'] = $config['settings']['numLimit'] ?? null;
        $data['amount'] = $config['settings']['amount'] ?? null;

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
                'manualLock' => $this->manualLock,
                'lightMode' => $this->lightMode,
                'lightRange' => $this->lightRange->toArray(),
                'soundEnable' => $this->soundEnable,
                'systemSoundEnable' => $this->systemSoundEnable,
                'volume' => $this->volume,
                'selectedSound' => $this->selectedSound,
                'language' => $this->language,
                'surplusControl' => $this->surplusControl,
                'surplus' => $this->surplus,
                'disturbMode' => $this->disturbMode,
                'disturbRange' => $this->disturbRange->toArray(),
                'd3SoundFirmware' => $this->d3SoundFirmware,
                'numLimit' => $this->numLimit,
                'amount' => $this->amount,
            ],
            'schedule' => $this->schedule
        ];
    }

}
