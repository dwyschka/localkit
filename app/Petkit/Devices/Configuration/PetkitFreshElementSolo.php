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
    private ?string $error;
    private ?string $workingState;
    private bool $feedSound = false;
    private bool $multiConfig = true;
    private array $foodWarnRange = [480,1200];
    private array $lightRange = [0, 1440];
    /**
     * @var int|mixed
     */
    private bool $shareOpen;
    /**
     * @var int|mixed
     */
    private bool $lightMode;
    /**
     * @var int|mixed
     */
    private bool $manualLock;

    private int $amount = 10;
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
                'amount' => $this->amount,
            ]
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

}
