<?php

namespace App\Homeassistant;

use App\Homeassistant\Concerns\MergesExtraPayload;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
class AlarmControlPanel extends BaseEntity
{
    use MergesExtraPayload;

    protected $entity = 'alarm_control_panel';
    protected $platform = 'alarm_control_panel';

    public function __construct(
        public string $technicalName,
        public string $name,
        public string $commandTopic,
        public string $icon = 'mdi:shield-home',
        public ?string $valueTemplate = null,
        public ?string $code = null,
        public bool $codeArmRequired = true,
        public bool $codeDisarmRequired = true,
        public bool $codeTriggerRequired = true,
        public string $payloadDisarm = 'DISARM',
        public string $payloadArmHome = 'ARM_HOME',
        public string $payloadArmAway = 'ARM_AWAY',
        public ?string $payloadArmNight = null,
        public ?string $payloadArmVacation = null,
        public ?string $payloadArmCustomBypass = null,
        public ?string $payloadTrigger = null,
        public array $supportedFeatures = ['arm_home', 'arm_away'],
        public ?string $payloadAvailable = null,
        public ?string $payloadNotAvailable = null,
        public ?string $availabilityTemplate = null,
        public ?string $availabilityTopic = null,
        public ?string $entityCategory = null,
        public ?string $uniqueId = null,
        public int $qos = 0,
        public bool $retain = false,
    ) {
    }

    public function payload(): array
    {
        $config = parent::payload();
        $config['payload_disarm'] = $this->payloadDisarm;
        $config['payload_arm_home'] = $this->payloadArmHome;
        $config['payload_arm_away'] = $this->payloadArmAway;
        $config['code_arm_required'] = $this->codeArmRequired;
        $config['code_disarm_required'] = $this->codeDisarmRequired;
        $config['code_trigger_required'] = $this->codeTriggerRequired;
        $config['supported_features'] = $this->supportedFeatures;

        return $this->withExtra($config, [
            'code' => $this->code,
            'payload_arm_night' => $this->payloadArmNight,
            'payload_arm_vacation' => $this->payloadArmVacation,
            'payload_arm_custom_bypass' => $this->payloadArmCustomBypass,
            'payload_trigger' => $this->payloadTrigger,
        ]);
    }
}
