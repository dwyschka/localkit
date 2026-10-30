<?php

namespace App\Petkit\Devices\PurobotMax;

use stdClass;
use Throwable;
use App\DTOs\MultiRangeDTO;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Homeassistant\EventPublisher;
use App\Homeassistant\HomeassistantTopic;
use App\Jobs\ServiceBle;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceContinue;
use App\Jobs\ServiceEnd;
use App\Jobs\ServicePower;
use App\Jobs\ServiceStart;
use App\Jobs\ServiceStop;
use App\Jobs\SetProperty;
use App\Models\BluetoothDevice;
use App\Models\Device as DeviceModel;
use App\Models\History;
use App\MQTT\GenericReply;
use App\Petkit\BluetoothDevices\BluetoothProxyInterface;
use App\Petkit\BluetoothDevices\Message;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * PetKit Purobot Max (T5) - a camera equipped self-cleaning litter box with
 * the N50 purifying-liquid spray module and the N60 deodorizer cartridge.
 *
 * Protocol notes, all confirmed against ctrl_t5 and the companion app:
 *
 *  - Work codes are shared across start/stop/continue/end, only the MQTT
 *    service topic differs:
 *      0 clean, 1 dump, 2 deodorize/spray, 3 recover, 4 smooth,
 *      6 calibration, 7 light, 9 maintenance.
 *    The app's own buttons: T5HomePresenter.cleanup() = 0, T5Dump() = 1,
 *    beginDeodorant() = 2, optionRecover() = 3, optionSmooth() = 4,
 *    T6CalibrateLitterTwoPresenter.controlDevice(..., 6, 1) = 6,
 *    lightTurnOn/Off() = 7, start/stop/endMaintenance() = 9.
 *    Pausing/resuming/stopping echo the currently running work mode
 *    (T5HomePresenter.pauseClean/continueToClean/terminateClean, which
 *    handle 0/1/3/4/5).
 *
 *  - There is no waste-bag/NFC mechanism: ctrl_t5's
 *    dispatch_handler_trash_bag_auth() is an empty stub, and neither the
 *    T5 home screen nor its settings screen exposes a pack/door action.
 *    The package_over event is still handled and only clears the cycle.
 *
 *  - The camera is cloud/P2P based (Agora/P2P), not the RTSP server the
 *    T6/T7 use. The T5 binaries ship a local `tserver` (HTTP-FLV) but no
 *    RTSP server, so this class deliberately does not implement HasCamera
 *    or Snapshot. Camera *settings* (camera/microphone/night/timeDisplay/
 *    cameraLight/cameraMultiRange) are fully wired over MQTT.
 */
class Device implements DeviceDefinition, BluetoothProxyInterface
{
    public static $workingStates = [
        DeviceStates::CLEANING, DeviceStates::IDLE, DeviceStates::PET_IN, DeviceStates::MAINTENANCE,
    ];

    // Work modes the app's pause/continue/terminate switch understands.
    // 5 is the "resume after pet interruption" mode the app also handles.
    private const WORK_MODE_CLEAN = 0;
    private const WORK_MODE_DUMP = 1;
    private const WORK_MODE_RECOVER = 3;
    private const WORK_MODE_SMOOTH = 4;
    private const WORK_MODE_RESUME = 5;
    private const WORK_MODE_CALIBRATE = 6;
    private const WORK_MODE_MAINTENANCE = 9;

    protected array $actions = [
        DeviceActions::START_CLEAN, DeviceActions::CLEAN_LITTER, DeviceActions::START_ODOUR,
        DeviceActions::RECOVER, DeviceActions::SMOOTH, DeviceActions::CALIBRATE,
        DeviceActions::POWER_ON, DeviceActions::POWER_OFF,
        DeviceActions::PAUSE_CLEAN, DeviceActions::CONTINUE_CLEAN, DeviceActions::TERMINATE_CLEAN,
        DeviceActions::START_LIGHTNING, DeviceActions::STOP_LIGHTNING,
        DeviceActions::START_MAINTENANCE, DeviceActions::STOP_MAINTENANCE,
        DeviceActions::RESET_N50, DeviceActions::RESET_N60,
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
            sprintf('/sys/%s/%s/thing/service/connect', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/ble', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_start/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_over/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_response/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/start', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/power', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/stop', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/continue', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/end', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public function stateTopics(): array
    {
        return [
            // ctrl_t5 reports its full state on the property event. The
            // payload is `params` directly (like the T6), but some firmware
            // builds wrap it in `params.state` - updateConfiguration()
            // accepts both.
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = $this->decodeState($message) ?? $message?->params;
                $workMode = $state?->workState?->workMode ?? null;
                $isWorking = isset($state->workState) && $workMode !== null;

                $device->update([
                    'working_state' => $isWorking
                        ? ((int) $workMode === self::WORK_MODE_MAINTENANCE ? DeviceStates::MAINTENANCE->value : DeviceStates::CLEANING->value)
                        : DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state),
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->handleWorkStart($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/work_continue/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->handleWorkStart($device, $message);
                $this->reply($topic, $message);
            },
            // Pausing leaves working_state on CLEANING - there is no PAUSED
            // state, and flipping to IDLE would hide continue/terminate until
            // the next work event.
            sprintf('/sys/%s/%s/thing/event/work_suspend/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->reply($topic, $message);
            },
            // Every completion event has to clear working_state or a cycle
            // stays stuck on CLEANING. Names are ctrl_t5's own
            // pk_event_pack_*_over_event_msg family.
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/dump_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/smooth_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/spray_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/liquid_reset_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/sand_correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/reset_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/package_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->finishCycle($device, $message);
                $this->reply($topic, $message);
            },
            // The light lifecycle: light_over means it stopped. Presence of
            // lightState in a property report means it is running.
            sprintf('/sys/%s/%s/thing/event/light_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = $this->decodeState($message);
                $configuration = $this->updateConfiguration($state, ['lightning' => false]);

                $device->update([
                    'configuration' => $configuration,
                ]);

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
                    EventPublisher::publish($device, 'in_use_start');
                }

                $device->update(['working_state' => DeviceStates::PET_IN->value]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                EventPublisher::publish($device, 'in_use_over');

                $device->update(['working_state' => DeviceStates::IDLE->value]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'DETECT',
                        'parameters' => json_decode($message->params->content ?? '{}', true),
                        'device_id' => $device->id,
                    ]);
                    EventPublisher::publish($device, 'detect');
                }

                $state = $this->decodeState($message);
                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state),
                ]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_discern/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'DETECT',
                        'parameters' => json_decode($message->params->content ?? '{}', true),
                        'device_id' => $device->id,
                    ]);
                }

                $state = $this->decodeState($message);
                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state),
                ]);
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
                    EventPublisher::publish($device, 'error_start');
                }

                $device->update(['error' => $content['err'] ?? $content['msg'] ?? null]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                EventPublisher::publish($device, 'error_over');

                $device->update(['error' => null]);
                $this->reply($topic, $message);
            },
        ];
    }

    private function decodeState(?stdClass $message): mixed
    {
        $raw = $message?->params?->state ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            return json_decode($raw, false);
        }

        return $raw;
    }

    private function handleWorkStart(DeviceModel $device, ?stdClass $message): void
    {
        $content = json_decode($message?->params?->content ?? '{}', true);
        $action = isset($content['action']) ? (int) $content['action'] : null;

        $workingState = $action === self::WORK_MODE_MAINTENANCE
            ? DeviceStates::MAINTENANCE->value
            : DeviceStates::CLEANING->value;

        $state = $this->decodeState($message);
        $configuration = $this->updateConfiguration($state, $action === null ? [] : ['workMode' => $action]);

        if (isset($message->params->event_id) && $action !== null) {
            History::updateOrCreate(['messageId' => $message->params->event_id], [
                'pet_id' => null,
                'type' => $workingState,
                'parameters' => $content,
                'device_id' => $device->id,
            ]);

            if ($action === self::WORK_MODE_CLEAN) {
                EventPublisher::publish($device, 'cleaning');
            } elseif ($action === self::WORK_MODE_MAINTENANCE) {
                EventPublisher::publish($device, 'maintenance');
            }
        }

        $device->update([
            'working_state' => $workingState,
            'configuration' => $configuration,
        ]);
    }

    private function finishCycle(DeviceModel $device, ?stdClass $message): void
    {
        $state = $this->decodeState($message);
        $configuration = $this->updateConfiguration($state, ['workMode' => null]);

        $device->update([
            'working_state' => DeviceStates::IDLE->value,
            'error' => $this->prepareErrorReporting($state),
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
        $workMode = $this->device->configuration['states']['workMode'] ?? null;

        return $workMode === null ? null : (int) $workMode;
    }

    public function hasAction(string $action): bool
    {
        $hasAction = in_array($action, $this->actions);

        switch ($action) {
            case DeviceActions::START_CLEAN:
            case DeviceActions::CLEAN_LITTER:
            case DeviceActions::START_ODOUR:
            case DeviceActions::RECOVER:
            case DeviceActions::SMOOTH:
            case DeviceActions::CALIBRATE:
            case DeviceActions::START_MAINTENANCE:
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::STOP_MAINTENANCE:
                return $hasAction && $this->device->working_state === DeviceStates::MAINTENANCE->value;

            case DeviceActions::START_LIGHTNING:
                return $hasAction && !($this->device->configuration['states']['lightning'] ?? false);

            case DeviceActions::STOP_LIGHTNING:
                return $hasAction && ($this->device->configuration['states']['lightning'] ?? false);

            case DeviceActions::PAUSE_CLEAN:
            case DeviceActions::CONTINUE_CLEAN:
            case DeviceActions::TERMINATE_CLEAN:
                $workMode = $this->currentWorkMode();

                $isRunningCycle = $this->device->working_state === DeviceStates::CLEANING->value
                    && in_array($workMode, [
                        self::WORK_MODE_CLEAN,
                        self::WORK_MODE_DUMP,
                        self::WORK_MODE_RECOVER,
                        self::WORK_MODE_SMOOTH,
                        self::WORK_MODE_RESUME,
                    ], true);

                $isMaintenance = $this->device->working_state === DeviceStates::MAINTENANCE->value
                    && $workMode === self::WORK_MODE_MAINTENANCE;

                return $hasAction && ($isRunningCycle || $isMaintenance);
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

            if ($key === 'cameraMultiRange' && $value instanceof MultiRangeDTO) {
                // The admin UI edits plain from/till minute pairs; ctrl_t5's
                // camera parser wants objects with rpt/time.
                $difference[$key] = $this->cameraRanges($value);
            } elseif ($value instanceof PetkitDTOInterface) {
                $difference[$key] = $value->toPetkitConfiguration();
            } elseif (is_numeric($value)) {
                $difference[$key] = (int) $value;
            } elseif (is_bool($value)) {
                $difference[$key] = (int) $value;
            }
        }

        if (empty($difference)) {
            return;
        }

        SetProperty::dispatchSync($device, $difference);
    }

    public function toHomeassistant()
    {
        $data = $this->configurationDefinition()->toArray();

        if (array_key_exists('lightning', $data['states'])) {
            $data['states']['lightning'] = (int) $data['states']['lightning'];
        }

        // MultiRange DTOs and the large raw arrays are not Home Assistant
        // entities - strip them from the discovery payload. The consumables
        // and states stay.
        $data['settings'] = array_filter(
            $data['settings'],
            fn (string $key) => ! Str::contains($key, 'MultiRange')
                && ! in_array($key, ['cameraMultiRange', 'detectMultiRange', 'schedule', 'soundList', 'sandSetUseConfig', 'sandFullWeight'], true),
            ARRAY_FILTER_USE_KEY
        );

        return json_encode($data);
    }

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach ($keys as $attributeName => $value) {
            if (property_exists($configuration, $attributeName)) {
                $configuration->$attributeName = $value;
            }
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
            case 'power_on':
                $this->powerOn($this->getDevice());
                break;
            case 'power_off':
                $this->powerOff($this->getDevice());
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
            case 'smooth':
                $this->smooth($this->getDevice());
                break;
            case 'calibrate':
                $this->calibrate($this->getDevice());
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
            case 'reset_n60':
                $this->resetN60($this->getDevice());
                break;
        }
    }

    public function resetWorkingState(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $configuration->workMode = null;
        $configuration->lightning = false;

        $record->update([
            'working_state' => DeviceStates::IDLE->value,
            'configuration' => $configuration->toArray(),
        ]);
    }

    public function startCleaning(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_CLEAN);
    }

    public function powerOn(DeviceModel $record): void
    {
        ServicePower::dispatchSync($record, 1);
    }

    public function powerOff(DeviceModel $record): void
    {
        ServicePower::dispatchSync($record, 0);
    }

    public function cleanLitter(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_DUMP);
    }

    public function startOdour(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 2);
    }

    public function recover(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_RECOVER);
    }

    public function smooth(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_SMOOTH);
    }

    /** Backwards-compatible alias for the shared "Level" action. */
    public function level(DeviceModel $record): void
    {
        $this->smooth($record);
    }

    public function calibrate(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_CALIBRATE);
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
        ServiceStart::dispatchSync($record, self::WORK_MODE_MAINTENANCE);
    }

    public function stopMaintenance(DeviceModel $record): void
    {
        ServiceEnd::dispatchSync($record, self::WORK_MODE_MAINTENANCE);
    }

    /**
     * Local bookkeeping only: the N50 reset itself is a cloud-only HTTP
     * route (T5Service.N50Reset), not an MQTT service.
     */
    public function resetN50(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->n50Durability;
        $nextChange = Carbon::now()->addDays((int) $durability);

        $configuration->n50NextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray(),
        ]);
    }

    /**
     * Same as resetN50: T5Service.purificationReset() is a cloud HTTP route,
     * so only the Localkit-side reminder is updated here.
     */
    public function resetN60(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->n60Durability;
        $nextChange = Carbon::now()->addDays((int) $durability);

        $configuration->n60NextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray(),
        ]);
    }

    public function toOTA(): array
    {
        return [];
    }

    /**
     * Flat schedule rows for ctrl_t5's /dev_schedule_get response. The T5
     * schedule model is the app/cloud one (id/type/time/repeats), not the
     * T6/T7 day-group model. Localkit currently stores the array read-only,
     * so this is normally empty and, importantly, not the generic
     * DevScheduleGetResource dummy schedule that would make the device
     * clean at fixed times nobody configured.
     */
    public function toScheduleGet(): array
    {
        $schedule = $this->device->configuration['schedule'] ?? [];

        if (! is_array($schedule)) {
            return [];
        }

        $rows = [];
        foreach ($schedule as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rows[] = [
                'id' => $item['id'] ?? 0,
                'deviceId' => $this->device->petkit_id,
                'time' => (int) ($item['time'] ?? 0),
                'type' => (int) ($item['type'] ?? 0),
                'repeats' => (string) ($item['repeats'] ?? '1,2,3,4,5,6,7'),
                'updatedAt' => now()->timestamp,
            ];
        }

        return $rows;
    }

    /**
     * /dev_feed_get response. The T5 does not use the T6/T7 feeder day-group
     * payload, but ctrl_t5 still calls this endpoint, so answer with the
     * flat schedule instead of letting the generic resource 500.
     */
    public function toFeedGet(): array
    {
        return [
            'schedule' => $this->toScheduleGet(),
            'nextTick' => 0,
            'latest' => [],
        ];
    }

    public function toDevSignup(): array
    {
        $config = $this->configurationDefinition()->toArray()['settings'];

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'locale' => $this->device->locale,
            'shareOpen' => (int) $config['shareOpen'],
            'typeCode' => (int) ($config['typeCode'] ?? 0),
        ];
    }

    public function toDeviceInfo(): array
    {
        $configuration = $this->configurationDefinition()->toArray();
        $config = $configuration['settings'];
        $capacity = $configuration['capacity'] ?? [];

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'signupAt' => $this->device->created_at->format('Y-m-d\TH:i:s.v\+0000'),
            'locale' => $this->device->locale,
            'shareOpen' => (int) $config['shareOpen'],
            'autoUpgrade' => (int) $config['autoUpgrade'],
            'modelCode' => 0,
            'familyId' => 0,
            'btMac' => $this->device->bt_mac,
            'typeCode' => (int) $config['typeCode'],
            'settings' => [
                'manualLock' => (int) $config['manualLock'],
                'clickOkEnable' => (int) $config['clickOkEnable'],
                'lightMode' => (int) $config['lightMode'],
                'softMode' => (int) $config['softMode'],
                'toiletLight' => (int) $config['toiletLight'],
                'toiletDetection' => (int) $config['toiletDetection'],
                'lightAssist' => (int) $config['lightAssist'],
                'phDetection' => (int) $config['phDetection'],
                'voice' => (int) $config['voice'],
                'detectInterval' => (int) $config['detectInterval'],
                'autoWork' => (int) $config['autoWork'],
                'autoIntervalMin' => (int) $config['autoIntervalMin'],
                'fixedTimeClear' => (int) $config['fixedTimeClear'],
                'avoidRepeat' => (int) $config['avoidRepeat'],
                'underweight' => (int) $config['underweight'],
                'kitten' => (int) $config['kitten'],
                'bury' => (int) $config['bury'],
                'deepClean' => (int) $config['deepClean'],
                'tumbling' => (int) $config['tumbling'],
                'sandType' => (int) $config['sandType'],
                'unit' => (int) $config['unit'],
                'sandSaving' => (int) $config['sandSaving'],
                'downpos' => (int) $config['downpos'],
                'stillTime' => (int) $config['stillTime'],
                'lightest' => (int) $config['lightest'],
                'disturbMode' => (int) $config['disturbMode'],
                'toneMode' => (int) $config['toneMode'],
                'soundEnable' => (int) $config['soundEnable'],
                'systemSoundEnable' => (int) $config['systemSoundEnable'],
                'volume' => (int) $config['volume'],
                'selectedSound' => (int) $config['selectedSound'],
                'upload' => (int) $config['upload'],
                'language' => (string) $config['language'],
                'autoSpray' => (int) $config['autoSpray'],
                'deepSpray' => (int) $config['deepSpray'],
                'fixedTimeSpray' => (int) $config['fixedTimeSpray'],
                'sprayDays' => (int) $config['sprayDays'],
                'deodor_tip_en' => (int) $config['deodor_tip_en'],
                'camera' => (int) $config['camera'],
                'microphone' => (int) $config['microphone'],
                'night' => (int) $config['night'],
                'timeDisplay' => (int) $config['timeDisplay'],
                'cameraLight' => (int) $config['cameraLight'],
                'preLive' => (int) $config['preLive'],
                'moveTracking' => (int) $config['moveTracking'],
                'moveSensitivity' => (int) $config['moveSensitivity'],
                'petDetection' => (int) $config['petDetection'],
                'petTracking' => (int) $config['petTracking'],
                'petSensitivity' => (int) $config['petSensitivity'],
                'stopTime' => (int) $config['stopTime'],
            ],
            'userId' => '1',
            'multiConfig' => (bool) $config['multiConfig'],
            'capacity' => $capacity,
            'cloudProduct' => [],
            'serviceStatus' => (int) $config['serviceStatus'],
            'hertz' => (int) $config['hertz'],
        ];
    }

    public function toDeviceMultiConfig(): array
    {
        $setting = $this->getDevice()->configuration();

        return [
            'distrubMultiRange' => $this->rangePairs($setting->distrubMultiRange),
            'lightMultiRange' => $this->rangePairs($setting->lightMultiRange),
            'toneMultiRange' => $this->rangePairs($setting->toneMultiRange),
            'cameraMultiRange' => $this->cameraRanges($setting->cameraMultiRange),
            'detectMultiRange' => $this->rangePairs($setting->detectMultiRange),
        ];
    }

    /**
     * ctrl_t5's /dev_multi_config parser expects each range as a plain
     * [from,till] pair, not the admin form's {from,till} object and not a
     * MultiRangeDTO wrapper.
     */
    private function rangePairs(mixed $range): array
    {
        if (is_string($range)) {
            $range = json_decode($range, true);
        }

        if ($range instanceof \App\DTOs\MultiRangeDTO) {
            $range = $range->ranges;
        }

        if (! is_array($range)) {
            return [];
        }

        $pairs = [];
        foreach ($range as $item) {
            if (is_array($item) && array_key_exists('from', $item) && array_key_exists('till', $item)) {
                $pairs[] = [(int) $item['from'], (int) $item['till']];
            } elseif (is_array($item) && array_key_exists(0, $item) && array_key_exists(1, $item)) {
                $pairs[] = [(int) $item[0], (int) $item[1]];
            }
        }

        return $pairs;
    }

    /**
     * Camera working time must reach ctrl_t5 as objects
     * ({enable, rpt, time:[[from,till],...]}), because that is what
     * pk_parse_cameraMultiNew_func() reads. The admin form keeps the raw
     * shape, but normalise time pairs here in case an older save used the
     * {from,till} form.
     */
    private function cameraRanges(mixed $range): array
    {
        if (is_string($range)) {
            $range = json_decode($range, true);
        }

        if ($range instanceof MultiRangeDTO) {
            $range = $range->ranges;
        }

        if (! is_array($range)) {
            return [];
        }

        // Preserve the old object shape (including its weekday rpt) if the
        // stored config still has it. Otherwise the admin form's simple
        // from/till pairs apply to every day.
        $objects = [];
        $hasObjectShape = false;
        foreach ($range as $item) {
            if (is_array($item) && (array_key_exists('time', $item) || array_key_exists('rpt', $item))) {
                $hasObjectShape = true;
                break;
            }
        }

        if ($hasObjectShape) {
            foreach ($range as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $time = $item['time'] ?? [];
                $pairs = [];
                if (is_array($time)) {
                    foreach ($time as $pair) {
                        if (is_array($pair) && array_key_exists('from', $pair) && array_key_exists('till', $pair)) {
                            $pairs[] = [(int) $pair['from'], (int) $pair['till']];
                        } elseif (is_array($pair) && array_key_exists(0, $pair) && array_key_exists(1, $pair)) {
                            $pairs[] = [(int) $pair[0], (int) $pair[1]];
                        }
                    }
                }

                $objects[] = [
                    'enable' => (int) ($item['enable'] ?? 1),
                    'rpt' => (string) ($item['rpt'] ?? '1,2,3,4,5,6,7'),
                    'time' => $pairs,
                ];
            }

            return $objects;
        }

        $pairs = $this->rangePairs($range);

        return $pairs === [] ? [] : [[
            'enable' => 1,
            'rpt' => '1,2,3,4,5,6,7',
            'time' => $pairs,
        ]];
    }

    private function updateConfiguration(mixed $content, array $extra = []): array
    {
        $settings = $this->getDevice()->configuration();

        try {
            if (is_object($content)) {
                $state = $content->state ?? $content;

                if (is_string($state)) {
                    $state = json_decode($state, false);
                }

                if (! is_object($state)) {
                    return $settings->toArray();
                }

                // IP is embedded in ctrl_t5's `other` string as Ip:"x.x.x.x"
                // (optionally quoted/escaped).
                $pattern = '/(?:^|,)Ip:\\\\?"(\d{1,3}(?:\.\d{1,3}){3})\\\\?"/';
                $match = Str::of((string) ($state->other ?? ''))->match($pattern);

                if ($match->value() !== null && ! $settings->ipAddressOverride) {
                    $settings->ipAddress = $match->value();
                }

                if (isset($state->workState->workMode)) {
                    $settings->workMode = (int) $state->workState->workMode;
                }

                if (property_exists($state, 'lightState')) {
                    $settings->lightning = $state->lightState !== null;
                }

                $source = $state->settings ?? $state;

                if (is_string($source)) {
                    $source = json_decode($source, false);
                }

                if (is_array($source)) {
                    $source = (object) $source;
                }

                if (! is_object($source)) {
                    $source = new \stdClass();
                }

                $scalarSettings = [
                    'shareOpen', 'multiConfig', 'autoUpgrade', 'typeCode', 'serviceStatus', 'hertz',
                    'camera', 'microphone', 'night', 'timeDisplay', 'cameraLight', 'preLive',
                    'petDetection', 'moveTracking', 'moveSensitivity', 'petTracking', 'petSensitivity',
                    'toiletDetection', 'phDetection', 'voice', 'detectInterval',
                    'lightMode', 'lightAssist', 'toiletLight',
                    'autoWork', 'autoIntervalMin', 'fixedTimeClear', 'avoidRepeat', 'underweight',
                    'kitten', 'bury', 'deepClean', 'tumbling', 'lightest', 'stillTime',
                    'sandType', 'unit', 'sandSaving', 'disturbMode', 'downpos', 'softMode', 'stopTime',
                    'toneMode', 'systemSoundEnable', 'soundEnable', 'volume', 'selectedSound',
                    'manualLock', 'clickOkEnable',
                    'autoSpray', 'deepSpray', 'fixedTimeSpray', 'sprayDays', 'deodor_tip_en',
                    'upload', 'language',
                ];

                foreach ($scalarSettings as $key) {
                    if (isset($source->$key)) {
                        $settings->$key = $source->$key;
                    }
                }
            }

            foreach ($extra as $property => $value) {
                if (property_exists($settings, $property)) {
                    $settings->$property = $value;
                }
            }

            return $settings->toArray();
        } catch (Throwable $exception) {
            Log::error('T5 Update', [
                'msg' => $exception->getMessage(),
            ]);
        }

        return $settings->toArray();
    }

    private function prepareErrorReporting(mixed $state): ?string
    {
        $error = $state->err ?? $state->errorMsg ?? $state->errorCode ?? null;

        if ($error === null || $error === '' || $error === 0 || $error === '0') {
            return null;
        }

        return (string) $error;
    }

    public function btConnect(BluetoothDevice $btDevice): void
    {
        ServiceConnect::dispatchSync(
            $this->getDevice(), $btDevice,
        );
    }

    public function btWrite(BluetoothDevice $btDevice, string $commandBase64, int $cmd): void
    {
        ServiceBle::dispatchSync(
            $this->getDevice(), $btDevice, $commandBase64, $cmd,
        );
    }
}
