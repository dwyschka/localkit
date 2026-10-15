<?php

namespace App\Petkit\Devices\PurobotMax;

use stdClass;
use App\Helpers\JsonHelper;
use App\Homeassistant\HomeassistantTopic;
use App\Jobs\ServiceContinue;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Jobs\ServiceStop;
use App\Jobs\SetProperty;
use App\Models\Device as DeviceModel;
use App\Models\History;
use App\MQTT\GenericReply;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use App\DTOs\PetkitDTOInterface;
use Carbon\Carbon;
use PhpMqtt\Client\Facades\MQTT;

/**
 * PetKit Purobot Max (T5). See Configuration.php for the action-code and
 * scope notes shared with this class.
 */
class Device implements DeviceDefinition
{
    public static $workingStates = [
        DeviceStates::CLEANING, DeviceStates::IDLE, DeviceStates::PET_IN, DeviceStates::MAINTENANCE,
    ];

    // workMode values that pauseClean()/continueClean()/terminateClean() are
    // allowed to echo back - the only ones t5/actions.txt found a confirmed
    // app-triggered start button for among the clean/dump/recover/smooth
    // family. 2 (spray) is excluded on purpose: no stop/continue/end call
    // for it was found anywhere in the decompiled app.
    private const WORK_MODE_CLEAN = 0;
    private const WORK_MODE_DUMP = 1;
    private const WORK_MODE_RECOVER = 3;
    private const WORK_MODE_SMOOTH = 4;

    protected array $actions = [
        DeviceActions::START_CLEAN, DeviceActions::CLEAN_LITTER, DeviceActions::START_ODOUR,
        DeviceActions::RECOVER, DeviceActions::LEVEL,
        DeviceActions::PAUSE_CLEAN, DeviceActions::CONTINUE_CLEAN, DeviceActions::TERMINATE_CLEAN,
        DeviceActions::START_LIGHTNING, DeviceActions::STOP_LIGHTNING,
        DeviceActions::START_MAINTENANCE, DeviceActions::STOP_MAINTENANCE,
        DeviceActions::RESET_N50,
        DeviceActions::RESET_WORKING_STATE,
    ];

    public function __construct(protected DeviceModel $device)
    {
    }

    public static function deviceName()
    {
        return 'Purobot Max';
    }

    public function subscribedTopics(): array
    {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/start', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/stop', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/continue', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/end', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public function stateTopics(): array
    {
        return [
            // Drives working_state and tracks workMode for pause/continue/
            // terminate. action 9 is maintenance; everything else that
            // reaches here is one of the clean/dump/spray/recover/smooth
            // codes (light does not fire this topic).
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->handleWorkStart($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/work_continue/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->handleWorkStart($device, $message);
                $this->reply($topic, $message);
            },
            // Pausing deliberately leaves working_state on CLEANING - there
            // is no PAUSED state in DeviceStates, and flipping to IDLE here
            // would hide the continue/terminate buttons until the next
            // work_* event arrives.
            sprintf('/sys/%s/%s/thing/event/work_suspend/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->reply($topic, $message);
            },
            // Cycle completion - every one of these has to clear
            // working_state or a cycle leaves the device stuck. Names are
            // ctrl_t5's own pk_event_pack_*_over_event_msg family.
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/dump_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/sand_correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/smooth_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/spray_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_in/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'IN_USE',
                        'parameters' => json_decode($message->params->content ?? '{}', true),
                        'device_id' => $device->id,
                    ]);
                }

                $device->update(['working_state' => DeviceStates::PET_IN->value]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                $device->update(['working_state' => DeviceStates::IDLE->value]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content ?? '{}', true);

                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'ERROR',
                        'parameters' => $content,
                        'device_id' => $device->id,
                    ]);
                }

                $device->update(['error' => $content['err'] ?? null]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                $device->update(['error' => null]);
                $this->reply($topic, $message);
            },
        ];
    }

    private function handleWorkStart(DeviceModel $device, ?stdClass $message): void
    {
        $content = json_decode($message?->params?->content ?? '{}', true);
        $action = $content['action'] ?? null;

        $workingState = $action === 9 ? DeviceStates::MAINTENANCE->value : DeviceStates::CLEANING->value;

        $configuration = $device->configuration;
        $configuration['states']['workMode'] = $action;

        if (isset($message->params->event_id) && $action !== null) {
            History::updateOrCreate(['messageId' => $message->params->event_id], [
                'pet_id' => null,
                'type' => $workingState,
                'parameters' => $content,
                'device_id' => $device->id,
            ]);
        }

        $device->update([
            'working_state' => $workingState,
            'configuration' => $configuration,
        ]);
    }

    private function finishCycle(DeviceModel $device): void
    {
        $configuration = $device->configuration;
        $configuration['states']['workMode'] = null;

        $device->update([
            'working_state' => DeviceStates::IDLE->value,
            'configuration' => $configuration,
        ]);
    }

    private function mergeHistory(?string $messageId, ?string $rawContent): void
    {
        if ($messageId === null) {
            return;
        }

        $history = History::where('messageId', $messageId)->first();

        if ($history === null) {
            return;
        }

        $content = json_decode($rawContent ?? '{}', true) ?? [];

        $history->update([
            'parameters' => [
                ...$history->parameters,
                ...$content,
            ],
        ]);
    }

    private function reply(string $topic, ?stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    public function getDevice(): DeviceModel
    {
        return $this->device;
    }

    private function currentWorkMode(): ?int
    {
        return $this->device->configuration['states']['workMode'] ?? null;
    }

    public function hasAction(string $action): bool
    {
        $hasAction = in_array($action, $this->actions);

        switch ($action) {
            case DeviceActions::START_CLEAN:
            case DeviceActions::CLEAN_LITTER:
            case DeviceActions::START_ODOUR:
            case DeviceActions::RECOVER:
            case DeviceActions::LEVEL:
            case DeviceActions::START_MAINTENANCE:
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::STOP_MAINTENANCE:
                return $hasAction && $this->device->working_state === DeviceStates::MAINTENANCE->value;

            case DeviceActions::PAUSE_CLEAN:
            case DeviceActions::CONTINUE_CLEAN:
            case DeviceActions::TERMINATE_CLEAN:
                return $hasAction
                    && $this->device->working_state === DeviceStates::CLEANING->value
                    && in_array($this->currentWorkMode(), [
                        self::WORK_MODE_CLEAN, self::WORK_MODE_DUMP, self::WORK_MODE_RECOVER, self::WORK_MODE_SMOOTH,
                    ], true);
        }

        return $hasAction;
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration::fromDevice($this->getDevice());
    }

    public function resetConfiguration(): array
    {
        return (new Configuration([]))->toArray();
    }

    public function configuration()
    {
        return $this->configurationDefinition()->toArray();
    }

    public function propertyChange(DeviceModel $device): void
    {
        $difference = JsonHelper::difference($device->configuration['settings'], $device->getOriginal('configuration')['settings']);

        $dto = $this->configurationDefinition();

        foreach ($difference as $key => $val) {
            $value = $dto->$key;

            if ($value instanceof PetkitDTOInterface) {
                $difference[$key] = $value->toPetkitConfiguration();
            } else if (is_numeric($value)) {
                $difference[$key] = (int)$value;
            } else if (is_bool($value)) {
                $difference[$key] = (int)$value;
            }
        }

        if (empty($difference)) {
            return;
        }

        SetProperty::dispatchSync($device, $difference);
    }

    public function toHomeassistant()
    {
        return json_encode($this->configurationDefinition()->toArray());
    }

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach ($keys as $attributeName => $value) {
            $configuration->$attributeName = $value;
        }
        $this->getDevice()->update(['configuration' => $configuration]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(stdClass $message): void
    {
        $action = $message->action;
        switch ($action) {
            case 'start_cleaning':
                $this->startCleaning($this->getDevice());
                break;
            case 'dump_litter':
                $this->cleanLitter($this->getDevice());
                break;
            case 'start_odour':
                $this->startOdour($this->getDevice());
                break;
            case 'recover':
                $this->recover($this->getDevice());
                break;
            case 'level':
                $this->level($this->getDevice());
                break;
            case 'pause_clean':
                $this->pauseClean($this->getDevice());
                break;
            case 'continue_clean':
                $this->continueClean($this->getDevice());
                break;
            case 'terminate_clean':
                $this->terminateClean($this->getDevice());
                break;
            case 'start_lightning':
                $this->startLightning($this->getDevice());
                break;
            case 'stop_lightning':
                $this->stopLightning($this->getDevice());
                break;
            case 'start_maintenance':
                $this->startMaintenance($this->getDevice());
                break;
            case 'stop_maintenance':
                $this->stopMaintenance($this->getDevice());
                break;
            case 'reset_n50':
                $this->resetN50($this->getDevice());
                break;
        }
    }

    public function resetWorkingState(DeviceModel $record): void
    {
        $configuration = $record->configuration;
        $configuration['states']['workMode'] = null;

        $record->update([
            'working_state' => DeviceStates::IDLE->value,
            'configuration' => $configuration,
        ]);
    }

    public function startCleaning(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 0);
    }

    public function cleanLitter(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 1);
    }

    public function startOdour(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 2);
    }

    public function recover(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_RECOVER);
    }

    public function level(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_SMOOTH);
    }

    public function pauseClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceStop::dispatchSync($record, $workMode);
    }

    public function continueClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceContinue::dispatchSync($record, $workMode);
    }

    public function terminateClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceEnd::dispatchSync($record, $workMode);
    }

    public function startLightning(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 7);
    }

    public function stopLightning(DeviceModel $record): void
    {
        ServiceEnd::dispatchSync($record, 7);
    }

    public function startMaintenance(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 9);
    }

    public function stopMaintenance(DeviceModel $record): void
    {
        ServiceEnd::dispatchSync($record, 9);
    }

    /**
     * Local bookkeeping only, same as the PuraMax (T4) implementation - the
     * actual cartridge-counter reset for this module is a cloud-only HTTP
     * route (N50Reset, see t5/http.txt), not an MQTT service.
     */
    public function resetN50(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->n50Durability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->n50NextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray(),
        ]);
    }

    public function toOTA(): array
    {
        return [];
    }

    public function toDevSignup(): array
    {
        $config = $this->device->configuration['settings'];

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'locale' => $this->device->locale,
            'shareOpen' => (int)$config['shareOpen'],
            'typeCode' => (int)($config['typeCode'] ?? 0),
        ];
    }

    public function toDeviceInfo(): array
    {
        $config = $this->device->configuration['settings'];

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'signupAt' => $this->device->created_at->format('Y-m-d\TH:i:s.v\+0000'),
            'locale' => $this->device->locale,
            'shareOpen' => (int)$config['shareOpen'],
            'typeCode' => (int)$config['typeCode'],
            'multiConfig' => true,
            'settings' => [
                'manualLock' => (int)$config['manualLock'],
                'clickOkEnable' => (int)$config['clickOkEnable'],
                'lightMode' => (int)$config['lightMode'],
                'softMode' => (int)$config['softMode'],
                'toiletLight' => (int)$config['toiletLight'],
                'toiletDetection' => (int)$config['toiletDetection'],
                'lightAssist' => (int)$config['lightAssist'],
                'phDetection' => (int)$config['phDetection'],
                'autoWork' => (int)$config['autoWork'],
                'autoIntervalMin' => (int)$config['autoIntervalMin'],
                'fixedTimeClear' => (int)$config['fixedTimeClear'],
                'avoidRepeat' => (int)$config['avoidRepeat'],
                'underweight' => (int)$config['underweight'],
                'kitten' => (int)$config['kitten'],
                'bury' => (int)$config['bury'],
                'deepClean' => (int)$config['deepClean'],
                'tumbling' => (int)$config['tumbling'],
                'sandType' => (int)$config['sandType'],
                'unit' => (int)$config['unit'],
                'sandSaving' => (int)$config['sandSaving'],
                'downpos' => (int)$config['downpos'],
                'stillTime' => (int)$config['stillTime'],
                'lightest' => (int)$config['lightest'],
                'disturbMode' => (int)$config['disturbMode'],
                'toneMode' => (int)$config['toneMode'],
                'soundEnable' => (int)$config['soundEnable'],
                'systemSoundEnable' => (int)$config['systemSoundEnable'],
                'volume' => (int)$config['volume'],
                'selectedSound' => (int)$config['selectedSound'],
                'voice' => (int)$config['voice'],
                'upload' => (int)$config['upload'],
                'autoSpray' => (int)$config['autoSpray'],
                'deepSpray' => (int)$config['deepSpray'],
                'fixedTimeSpray' => (int)$config['fixedTimeSpray'],
                'sprayDays' => (int)$config['sprayDays'],
                'deodor_tip_en' => (int)$config['deodor_tip_en'],
            ],
        ];
    }

    public function toDeviceMultiConfig(): array
    {
        $setting = $this->getDevice()->configuration();

        return [
            'distrubMultiRange' => $setting->distrubMultiRange ?? [],
            'lightMultiRange' => $setting->lightMultiRange ?? [],
            'toneMultiRange' => $setting->toneMultiRange ?? [],
        ];
    }
}
