<?php

namespace App\Petkit\Devices\Configuration;

use App\Helpers\HomeassistantHelper;
use App\Homeassistant\BinarySensor;
use App\Homeassistant\Button;
use App\Homeassistant\HASwitch;
use App\Homeassistant\Number;
use App\Homeassistant\Select;
use App\Homeassistant\Sensor;
use App\Models\Device;

class PetkitFreshElementSolo implements ConfigurationInterface
{
    private int $factor = 10;

    #[Sensor(
        technicalName: 'error',
        name: 'Error',
        icon: 'mdi:error',
        valueTemplate: '{{ value_json.states.error }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $error = null;

    #[Sensor(
        technicalName: 'device_status',
        name: 'Device Status',
        icon: 'mdi:information-outline',
        valueTemplate: '{{ value_json.states.state }}',
        entityCategory: 'diagnostic'
    )]
    private ?string $workingState = null;

    #[HASwitch(
        technicalName: 'food_warn',
        name: 'Refill alarm',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.foodWarn | string }}',
        commandTemplate: '{"foodWarn":{{ value }}}',
        payloadOn: "true",
        payloadOff: "false",
        deviceClass: 'switch'
    )]
    private bool $foodWarn = false;
    #[HASwitch(
        technicalName: 'feed_sound',
        name: 'Feeding chime',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.feedSound | string }}',
        commandTemplate: '{"feedSound":{{ value }}}',
        payloadOn: "true",
        payloadOff: "false",
        deviceClass: 'switch'
    )]
    private bool $feedSound = false;
    private bool $multiConfig = true;
    private array $foodWarnRange = [480,1200];
    private array $lightRange = [0, 1440];
    /**
     * @var int|mixed
     */
    private bool $shareOpen = false;
    /**
     * @var int|mixed
     */
    private bool $lightMode = true;


    #[HASwitch(
        technicalName: 'manual_lock',
        name: 'Child lock',
        commandTopic: 'setting/set',
        icon: 'mdi:toggle-switch',
        valueTemplate: '{{ value_json.settings.manualLock | string }}',
        commandTemplate: '{"manualLock":{{ value }}}',
        payloadOn: "True",
        payloadOff: "False",
        deviceClass: 'switch'
    )]
    private bool $manualLock;

    #[Select(
        technicalName: 'amount',
        name: 'Feed Amount',
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
        commandTemplate: ' {"amount": {{value}}}',
        entityCategory: 'config'
    )]
    private int $amount = 10;

    private array $schedule = [];

    #[Button(
        technicalName: 'action_feed',
        name: 'Feed',
        commandTopic: 'action/start',
        icon: 'mdi:information-outline',
        commandTemplate: '{"action": "feed"}',
        availabilityTemplate: 'online',
    )]
    private $actionFeed = 1;

    /**
     * Constructor that initializes the configuration from a Device object
     */
    public function __construct(private ?Device $device)
    {
        if (!is_null($device)) {
            $this->loadFromDevice();
        }
    }

    /**
     * Load configuration from the device
     */
    private function loadFromDevice(): void
    {
        // States
        $this->error = $this->device->error;
        $this->workingState = $this->device->working_state;

        $config = $this->device->configuration;

        $this->schedule = $config['schedule'] ?? [];

        if (isset($config['settings'])) {
            $settings = $config['settings'];

            $this->shareOpen = $settings['shareOpen'] ?? 0;
            $this->factor = $settings['factor'] ?? 10;
            $this->multiConfig = $settings['multiConfig'] ?? 1;
            $this->feedSound = $settings['feedSound'] ?? 0;
            $this->lightMode = $settings['lightMode'] ?? 0;
            $this->lightRange = $settings['lightRange'] ?? [0, 1440];
            $this->manualLock = $settings['manualLock'] ?? 0;
            $this->foodWarnRange = $settings['foodWarnRange'] ?? [480,1200];
            $this->foodWarn = $settings['foodWarn'] ?? 0;
            $this->amount = $settings['amount'] ?? 10;

        }
    }

    /**
     * Convert the configuration to an array
     */
    public function toArray(): array
    {
        return [
            'states' => [
                'error' => $this->error,
                'state' => $this->workingState,
            ],
            'settings' => [
                'shareOpen' => $this->shareOpen,
                'factor' => $this->factor,
                'feedSound' => $this->feedSound,
                'multiConfig' => $this->multiConfig,
                'lightMode' => $this->lightMode,
                'lightRange' => $this->lightRange,
                'manualLock' => $this->manualLock,
                'foodWarnRange' => $this->foodWarnRange,
                'foodWarn' => $this->foodWarn,
                'amount' => $this->amount,
            ],
            'schedule' => $this->schedule,
        ];
    }
    public function getDevice()
    {
        return $this->device;
    }

    public function getFeedSound(): bool
    {
        return $this->feedSound;
    }

    public function setFeedSound(bool $feedSound): void
    {
        $this->feedSound = $feedSound;
    }

    public function getMultiConfig(): bool
    {
        return $this->multiConfig;
    }

    public function setMultiConfig(bool $multiConfig): void
    {
        $this->multiConfig = $multiConfig;
    }

    /**
     * @return int[]
     */
    public function getFoodWarnRange(): array
    {
        return $this->foodWarnRange;
    }

    /**
     * @param int[] $foodWarnRange
     */
    public function setFoodWarnRange(array $foodWarnRange): void
    {
        $this->foodWarnRange = $foodWarnRange;
    }

    /**
     * @return int[]
     */
    public function getLightRange(): array
    {
        return $this->lightRange;
    }

    /**
     * @param int[] $lightRange
     */
    public function setLightRange(array $lightRange): void
    {
        $this->lightRange = $lightRange;
    }

    public function getShareOpen(): bool
    {
        return $this->shareOpen;
    }

    public function setShareOpen(bool $shareOpen): void
    {
        $this->shareOpen = $shareOpen;
    }

    public function getFactor(): int
    {
        return $this->factor;
    }

    public function setFactor(int $factor): void
    {
        $this->factor = $factor;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getSchedule(): array
    {
        return $this->schedule;
    }

    public function setSchedule(array $schedule): void
    {
        $this->schedule = $schedule;
    }

    public function getManualLock(): bool
    {
        return $this->manualLock;
    }

    public function setManualLock(bool $manualLock): void
    {
        $this->manualLock = $manualLock;
    }

}
