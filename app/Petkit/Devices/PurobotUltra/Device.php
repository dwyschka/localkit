<?php

namespace App\Petkit\Devices\PurobotUltra;

use stdClass;
use Throwable;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\EventPublisher;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\ServiceBle;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceContinue;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Jobs\ServiceStop;
use App\Jobs\SetProperty;
use App\Jobs\TakeSnapshot;
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
 * PetKit PuroBot Ultra (T6) - a camera equipped self-cleaning litter box with
 * a waste-packaging unit, a sealing door and a pan motor.
 *
 * Derived from the PuroBot Crystal (T7): same Aliyun thing-model protocol, same
 * services, same settings mechanics. What differs, and why:
 *
 *  - No N60 cartridge and no cardboard liner, so neither consumable and neither
 *    reset action exists here.
 *  - No spray unit, so no spray_over topic. This device finishes its cycles on
 *    dump_over / smooth_over / melt_over / correct_over / sand_correct_over /
 *    package_over instead - the names come from ctrl_t6's own
 *    pk_event_pack_*_over_event_msg symbols, and every one of them has to clear
 *    working_state or a cycle leaves the device stuck on CLEANING.
 *  - It posts wander events of its own (wander_over).
 *  - The pan motor is NOT reachable from here. It belongs to the vendor ctrl
 *    daemon and is driven by a dispatch message, not an MQTT service; see rptz
 *    in the raptor tree. `userHomeMode.ptz` is only an on/off override.
 *
 * The work-mode numbers sent on thing/service/start are the family's: 0 clean,
 * 2 deodorize, 7 lightning are each confirmed by two independent litter boxes
 * (t4 PuraMax and t7), 4 level by the t7. ctrl_t6 itself passes every number
 * straight through to the HMI without interpreting it
 * (dispatch_handler_start_action -> pk_WAN_send_ctrl_action_2_HMI), so none of
 * them can be read out of the firmware alone - but the companion app
 * (com.petkit.oversea 13.4.1, T6*Presenter classes) hard-codes the numbers it
 * sends for every one of its own buttons, which confirms the remaining codes:
 *   - 1  dump      (T6SandGuideActivity.preparePack / "倾空" has no start of
 *                    its own - it is a work-mode the device enters on its own
 *                    during a clean cycle; the app only offers pause/continue/
 *                    terminate for whatever work mode is currently running,
 *                    see pauseClean()/continueClean()/terminateClean() below)
 *   - 3  recover    ("复位", T6HomePresenter.optionRecover - resets the pan
 *                    motor to its parked position; completes on correct_over/
 *                    sand_correct_over)
 *   - 9  pack       ("打包", T6HomePresenter/T6PackPresenter.startBagging -
 *                    the only thing that triggers ctrl_t6's NFC/bag-auth call
 *                    to the Petkit cloud, dispatch_handler_trash_bag_auth ->
 *                    net_get_dev_package_auth -> GET /dev_package_auth;
 *                    completes on package_over)
 *   - 11 door       ("打开密封门") - a single toggle, not an open/close pair:
 *                    the app always sends start_action:11 regardless of the
 *                    door's current state (T6HomePresenter.optionDoor() calls
 *                    controlDoor(deviceId, 1) unconditionally) and the device
 *                    decides open vs. close itself from its own hall sensors.
 *                    No dedicated *_over topic exists for it; its state comes
 *                    back on the top-level `door` field of property/post
 *                    (already read in prepareErrorReporting()).
 * "melt" has no known trigger (no button for it was found in the app under
 * any Chinese or English label) and is deliberately left listen-only.
 *
 * Pausing/resuming/stopping a running cycle (any of clean/dump/recover/level)
 * is not its own start_action - the app re-sends whichever code is already
 * active as a stop_action (pause), continue_action (resume) or end_action
 * (stop), read from the device's own reported work mode
 * (T6HomePresenter.pauseClean/continueToClean/terminateClean(long) - the
 * 1-arg overloads, not the 3-arg ones used by the dedicated guide screens).
 * This is why $workMode is tracked in Configuration: pauseClean() etc. below
 * need to know which code is currently running to echo it back correctly.
 */
class Device implements DeviceDefinition, Snapshot, BluetoothProxyInterface
{
    public static $workingStates = [
        DeviceStates::CLEANING, DeviceStates::IDLE, DeviceStates::PET_IN,
    ];
    protected array $actions = [
        DeviceActions::START_CLEAN, DeviceActions::DEODORIZE, DeviceActions::LEVEL, DeviceActions::TAKE_SNAPSHOT,
        DeviceActions::RESET_WORKING_STATE,
        DeviceActions::START_LIGHTNING, DeviceActions::STOP_LIGHTNING,
        DeviceActions::PACK_WASTE_BAG, DeviceActions::RECOVER, DeviceActions::TOGGLE_DOOR,
        DeviceActions::PAUSE_CLEAN, DeviceActions::CONTINUE_CLEAN, DeviceActions::TERMINATE_CLEAN,
        DeviceActions::RESET_DESICCANT,
    ];

    /**
     * start_action/stop_action/continue_action/end_action codes for the work
     * modes this device reports on its own (property/post's workState.workMode)
     * - see the class docblock for how each one was confirmed. Used by
     * pauseClean()/continueClean()/terminateClean() to echo back whatever is
     * currently running, exactly like the companion app's 1-arg
     * pauseClean/continueToClean/terminateClean do.
     *
     * Deliberately does not include 9 (pack): the app's generic pause/
     * continue/terminate switch (T6HomePresenter's 1-arg overloads) only
     * handles work modes 0/1/3/4/5, so packing a bag is not interruptible
     * through this mechanism on the real device either - its only control is
     * packWasteBag() below.
     */
    private const WORK_MODE_CLEAN = 0;
    private const WORK_MODE_DUMP = 1;
    private const WORK_MODE_RECOVER = 3;
    private const WORK_MODE_LEVEL = 4;

    public function __construct(protected DeviceModel $device)
    {

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
            sprintf('/sys/%s/%s/thing/service/end', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public static function deviceName()
    {
        return 'Purobot Ultra';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                // This event reports the state directly on `params`, not nested under `params.state`.
                // The IP address is not a separate field here either - it's embedded in `other`, parsed in updateConfiguration().
                // This is the only topic driving `working_state` for this device: presence of `workState` means cleaning is in progress.
                $state = $message?->params;

                $device->update([
                    'working_state' => isset($state->workState) ? DeviceStates::CLEANING->value : DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/move_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
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

                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            // pet_in/pet_out share one event_id (like D4SH's eat_start/
            // eat_over) - no weight sensor on this device, so unlike
            // PuraMax's pet_out this can't match a pet by weight.
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
            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                EventPublisher::publish($device, 'in_use_over');
            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content ?? '{}', true);

                // action 0 is the same "start cleaning" code used when we send
                // the command ourselves (see startCleaning()) - other action
                // codes (2 = deodorize, 4 = level, 7 = lightning) are already
                // reported through their own dedicated *_over topics.
                if (isset($message->params->event_id) && ($content['action'] ?? null) === 0) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'CLEANING',
                        'parameters' => $content,
                        'device_id' => $device->id,
                    ]);
                    EventPublisher::publish($device, 'cleaning');
                }

                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/light_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $conf =  $this->updateConfiguration($state, ['lightning' => isset($state->lightState)]);

                // The light lifecycle is driven entirely through this topic: when the light is
                // running the state carries a `lightState` object, and it's absent once it stops.
                $device->update([
                    'configuration' => $conf
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            /*
             * Cycle completion. Everything this device can finish has to land
             * here: property/post is the only topic that drives working_state
             * and it cannot tell one cycle from another, so a cycle whose
             * *_over topic is unhandled leaves working_state stuck on
             * CLEANING. The names are ctrl_t6's own
             * pk_event_pack_*_over_event_msg family; the bodies are identical
             * because none of them carries anything clean_over does not.
             *
             * No History type fits these (they are not one of
             * IN_USE/CLEANING/MAINTENANCE/ERROR/DETECT), so they only clear
             * the state, exactly like clean_over.
             */
            sprintf('/sys/%s/%s/thing/event/dump_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/smooth_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/melt_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/sand_correct_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/package_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/wander_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            // error_start/error_over share one event_id, like drink_start/
            // drink_over on the W7H.
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

                $device->update(['error' => $content['err'] ?? null]);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                EventPublisher::publish($device, 'error_over');

                $device->update(['error' => null]);
            },
        ];
    }

    /**
     * Merges a follow-up event's content into the History row created for
     * the event it belongs to (found by messageId - the same event_id for
     * start/over pairs like pet_in/pet_out or error_start/error_over).
     * Silently does nothing if there's no matching row.
     */
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

    public function hasAction(string $action): bool
    {
        $hasAction = in_array($action, $this->actions);

        switch ($action) {
            case DeviceActions::START_CLEAN:
            case DeviceActions::DEODORIZE:
            case DeviceActions::LEVEL:
            case DeviceActions::PACK_WASTE_BAG:
            case DeviceActions::RECOVER:
                // Same gating the vendor app uses for its own "Jetzt
                // reinigen"/"抚平"/"复位"/"打包" buttons: offered only while
                // nothing else is running.
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::START_LIGHTNING:
                return $hasAction && !($this->device->configuration['states']['lightning'] ?? false);

            case DeviceActions::STOP_LIGHTNING:
                return $hasAction && ($this->device->configuration['states']['lightning'] ?? false);

            case DeviceActions::TOGGLE_DOOR:
                // The vendor app masks the door button during cleaning and
                // most error states (see T6BottomView.disableDoor() call
                // sites) - not offering it outside IDLE keeps that without
                // reproducing its full per-error-code table here.
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::PAUSE_CLEAN:
            case DeviceActions::CONTINUE_CLEAN:
            case DeviceActions::TERMINATE_CLEAN:
                // Only meaningful while a cycle is actually running - and
                // only for work modes the app's own pause/continue/terminate
                // switch understands (see WORK_MODE_* above); packing (9) is
                // excluded there on purpose.
                return $hasAction
                    && $this->device->working_state === DeviceStates::CLEANING->value
                    && in_array($this->currentWorkMode(), [
                        self::WORK_MODE_CLEAN, self::WORK_MODE_DUMP, self::WORK_MODE_RECOVER, self::WORK_MODE_LEVEL,
                    ], true);
        }

        return $hasAction;
    }

    /**
     * The work mode the device last reported on property/post's
     * workState.workMode - null while idle or before the first state update.
     */
    private function currentWorkMode(): ?int
    {
        $workMode = $this->device->configuration['states']['workMode'] ?? null;

        return $workMode === null ? null : (int) $workMode;
    }

    public function takeSnapshot(DeviceModel $record): void
    {
        TakeSnapshot::dispatchSync($record);
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

    /**
     * Local bookkeeping only, same as FreshElementSolo/YumshareSolo/
     * FreshElement3 - no app screen or wire key for a desiccant was found on
     * this device, so there is nothing to send it over MQTT either.
     */
    public function resetDesiccant(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->desiccantDurability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->desiccantNextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray(),
        ]);
    }

    /**
     * Settings and schedule can both change in the same save (e.g. toggling
     * camera while also editing feed times) - the device takes those
     * together in one property_set, so both diffs are merged into a single
     * dispatch rather than the settings diff silently winning over a
     * schedule change or vice versa.
     */
    public function propertyChange(DeviceModel $device): void
    {
        $difference = JsonHelper::difference($device->configuration['settings'], $device->getOriginal('configuration')['settings']);
        $scheduleChanged = !empty(JsonHelper::difference($device->configuration['schedule'], $device->getOriginal('configuration')['schedule']));

        $dto = $this->configurationDefinition();

        foreach ($difference as $key => $val) {
            $value = $dto->$key;

            if($value instanceof PetkitDTOInterface) {
                $difference[$key] = $value->toPetkitConfiguration();
            } else if (is_numeric($value)) {
                $difference[$key] = (int)$value;
            } else if (is_bool($value)) {
                $difference[$key] = (int)$value;
            }
        }

        if ($scheduleChanged) {
            $difference['feed'] = $this->toFeed($device);
        }

        if (empty($difference)) {
            return;
        }

        SetProperty::dispatchSync($device, $difference);
    }

    public function toFeed(DeviceModel $device): string
    {
        // configuration['schedule'] is now just a pointer (['key'=>..,
        // 'checksum'=>..]) - the actual day-groups live in
        // device_schedules/device_schedule_items, see Device::scheduleGroups().
        $key = $device->configuration['schedule']['key'] ?? 'default';
        $schedule = $device->scheduleGroups($key);

        $latest = Time::calculateLatest($schedule);
        $nextTick = last($latest) ?: ['a' => 0, 'id' => '', 't' => 0];

        return json_encode([
            'schedule' => array_map(
                fn(array $s) => Time::normalizeScheduleGroupForWire($s),
                $schedule
            ),
            'nextTick' => $nextTick['t'],
            'latest' => $latest
        ]);
    }

    public function toHomeassistant()
    {
        $data = $this->configurationDefinition()->toArray();

        if (array_key_exists('lightning', $data['states'])) {
            $data['states']['lightning'] = (int) $data['states']['lightning'];
        }

        // MultiRange configs are not exposed to Home Assistant - strip them from the base json.
        $data['settings'] = array_filter(
            $data['settings'],
            fn (string $key) => !Str::contains($key, 'MultiRange'),
            ARRAY_FILTER_USE_KEY
        );

        return json_encode($data);
    }

    public function toSnapshot(): ?string
    {
        return $this->configurationDefinition()->toSnapshot();
    }

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach($keys as $attributeName => $value) {
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
            case 'snapshot':
                $this->takeSnapshot($this->getDevice());
                break;
            case 'deodorize':
                $this->deodorize($this->getDevice());
                break;
            case 'level':
                $this->level($this->getDevice());
                break;
            case 'start_lightning':
                $this->startLightning($this->getDevice());
                break;
            case 'stop_lightning':
                $this->stopLightning($this->getDevice());
                break;
            case 'pack_waste_bag':
                $this->packWasteBag($this->getDevice());
                break;
            case 'recover':
                $this->recover($this->getDevice());
                break;
            case 'toggle_door':
                $this->toggleDoor($this->getDevice());
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
        }
    }



    public function resetWorkingState(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $configuration->lightning = false;

        $record->update([
            'working_state' => DeviceStates::IDLE->value,
            'configuration' => $configuration->toArray()
        ]);
    }

    public function startCleaning(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 0);
    }

    public function deodorize(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 2);
    }

    public function level(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 4);
    }

    public function startLightning(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 7);
    }

    public function stopLightning(DeviceModel $record): void
    {
        ServiceEnd::dispatchSync($record, 7);
    }

    /**
     * "打包" - manually pack a waste bag. This is the only command that makes
     * ctrl_t6 call out to the Petkit cloud for NFC/bag authentication
     * (dispatch_handler_trash_bag_auth -> net_get_dev_package_auth -> GET
     * /dev_package_auth); completes on package_over.
     */
    public function packWasteBag(DeviceModel $record): void
    {
        // 9 - not in WORK_MODE_* on purpose, see that block's docblock.
        ServiceStart::dispatchSync($record, 9);
    }

    /**
     * "复位" - resets the pan motor to its parked position. Completes on
     * correct_over/sand_correct_over.
     */
    public function recover(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, self::WORK_MODE_RECOVER);
    }

    /**
     * "打开密封门" - the vendor app always sends start_action:11 for this,
     * whether the door is currently open or closed; the device reads its own
     * hall sensors to decide which way to move. There is deliberately no
     * separate open()/close() here for the same reason the app has none.
     */
    public function toggleDoor(DeviceModel $record): void
    {
        ServiceStart::dispatchSync($record, 11);
    }

    /**
     * Pauses whichever cycle is currently running, by re-sending its own
     * start_action code as a stop_action - exactly what the companion app's
     * 1-arg T6HomePresenter.pauseClean(long) does. hasAction() already
     * restricts this to the work modes the app's switch understands.
     */
    public function pauseClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceStop::dispatchSync($record, $workMode);
    }

    /**
     * Resumes a paused cycle - see pauseClean()'s docblock.
     */
    public function continueClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceContinue::dispatchSync($record, $workMode);
    }

    /**
     * Stops/terminates the currently running cycle - see pauseClean()'s
     * docblock.
     */
    public function terminateClean(DeviceModel $record): void
    {
        $workMode = $this->currentWorkMode();

        if ($workMode === null) {
            return;
        }

        ServiceEnd::dispatchSync($record, $workMode);
    }

    public function toOTA(): array
    {
        return [

        ];
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
            'typeCode' => (int)$config['typeCode'] ?? 0
        ];
    }

    public function toDeviceInfo(): array
    {
        $config = $this->device->configuration['settings'];
        $capacity = $this->device->configuration['capacity'];

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'signupAt' => $this->device->created_at->format('Y-m-d\TH:i:s.v\+0000'),
            'locale' => $this->device->locale,
            'shareOpen' => (int)$config['shareOpen'],
            'autoUpgrade' => (int)$config['autoUpgrade'],
            'modelCode' => 0,
            'familyId' => 0,
            'btMac' => $this->device->bt_mac,
            'typeCode' => (int)$config['typeCode'],
            'settings' => [
                'manualLock' => (int)$config['manualLock'],
                'clickOkEnable' => (int)$config['clickOkEnable'],
                'lightMode' => (int)$config['lightMode'],
                'toiletLight' => (int)$config['toiletLight'],
                'timestamp_enable' => (int)$config['timestamp_enable'],
                'camera' => (int)$config['camera'],
                'microphone' => (int)$config['microphone'],
                'night' => (int)$config['night'],
                'cameraLight' => (int)$config['cameraLight'],
                'preLive' => (int)$config['preLive'],
                'lightAssist' => (int)$config['lightAssist'],
                'moveDetection' => (int)$config['moveDetection'],
                'moveSensitivity' => (int)$config['moveSensitivity'],
                'petDetection' => (int)$config['petDetection'],
                'wanderDetection' => (int)$config['wanderDetection'],
                'toiletDetection' => (int)$config['toiletDetection'],
                'phDetection' => (int)$config['phDetection'],
                'detectInterval' => (int)$config['detectInterval'],
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
                'stillTime' => (int)$config['stillTime'],
                'lightest' => (int)$config['lightest'],
                'softMode' => (int)$config['softMode'],
                'disturbMode' => (int)$config['disturbMode'],
                'toneMode' => (int)$config['toneMode'],
                'soundEnable' => (int)$config['soundEnable'],
                'systemSoundEnable' => (int)$config['systemSoundEnable'],
                'volume' => (int)$config['volume'],
                'selectedSound' => (int)$config['selectedSound'],
                'voice' => (int)$config['voice'],
                'bag_tip_en' => (int)$config['bag_tip_en'],
                'spary_tip_en' => (int)$config['spary_tip_en'],
                'upload' => (int)$config['upload'],
                'log_upload' => (int)$config['log_upload'],
            ],
            'userId' => '1',
            'multiConfig' => $config['multiConfig'],
            'capacity' => $capacity,
            'cloudProduct' => [],
            'serviceStatus' => $config['serviceStatus'],
            'hertz' => $config['hertz']
        ];
    }

    public function toDeviceMultiConfig(): array {
        return [
            "detectMultiRange" => json_encode([
                "detectMultiRange" => [[0, 1440]]
            ]),
            "cameraMultiNew" => json_encode([
                "cameraMultiNew" => [
                    [
                        "enable" => 1,
                        "rpt" => "1,2,3,4,5,6,7",
                        "time" => [[0, 1440]]
                    ]
                ]
            ]),
            "toneMultiRange" => json_encode([
                "toneMultiRange" => [[1320, 360]]
            ]),
            "lightMultiRange" => json_encode([
                "lightMultiRange" => [[0, 1440]]
            ])
        ];
    }

    public function toFeedGet(): array
    {
        $unusedDays = [1,2,3,4,5,6,7];
        $key = $this->device->configuration['schedule']['key'] ?? 'default';
        $schedules = $this->device->scheduleGroups($key);
        $latest = Time::calculateLatest($schedules);
        $nextTick = last($latest) ?: ['a' => 0, 'id' => '', 't' => 0];

        foreach($schedules as &$schedule) {
            $schedule['itemJsonString'] = json_encode($schedule['it']);

            foreach(explode(',', $schedule['re']) as $re) {
                unset($unusedDays[intval($re) - 1]);
            }

        }

        if(!empty($unusedDays)) {
            $schedules[] = [
                're' => implode(',', $unusedDays),
                'it' => [],
                'itemJsonString' => '[]',
            ];
        }

        $schedules = array_map(
            fn(array $schedule) => Time::normalizeScheduleGroupForWire($schedule),
            $schedules
        );

        return [
            'schedule' => $schedules,
            'nextTick' => $nextTick['t'],
            'latest' => $latest
        ];
    }

    private function updateConfiguration(mixed $content, array $extra = []): array
    {

        $settings = $this->getDevice()->configuration();

        try {

            //IP - reported inside the `other` string, key/value may or may not be quoted (e.g. Ip:"x.x.x.x" or "Ip":x.x.x.x)
            $pattern = '/(?:^|,)Ip:\\\\?"(\d{1,3}(?:\.\d{1,3}){3})\\\\?"/';
            $match = Str::of($content->other)->match($pattern);

            if ($match->value() !== null) {
                $settings->ipAddress = $match->value();
            }

            // workState.workMode is the number pauseClean()/continueClean()/
            // terminateClean() echo back as a stop/continue/end_action - see
            // the class docblock. Only present while a cycle is running
            // (isset($state->workState) is what tells the caller to mark the
            // device CLEANING in the first place), so leave the stored value
            // alone otherwise rather than overwriting it with null.
            if (isset($content->workState->workMode)) {
                $settings->workMode = (int) $content->workState->workMode;
            }

            foreach ($extra as $property => $value) {
                $settings->$property = $value;
            }

            return $settings->toArray();
        } catch (Throwable $exception) {
            Log::error('T7 Update', [
                'msg' => $exception->getMessage(),
            ]);
        }

        return $settings->toArray();
    }

    private function prepareErrorReporting(mixed $state)
    {
        $error = null;

        if ($state?->door == 0) {
            $error = 'door_closed';
        }

        return $error;
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
