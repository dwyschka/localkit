<?php

namespace App\Petkit\Devices;

use stdClass;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\AddWaterReset;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Jobs\TakeSnapshot;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\Petkit\BluetoothDevices\BluetoothProxyInterface;
use App\Petkit\BluetoothDevices\Message;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use App\Petkit\Interfaces\HasCamera;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * Device definition for the PetKit W7H "Eversweet Ultra" smart fountain
 * (camera + auto water change + heater). See IMPLEMENT/w7h_config_keys.csv and
 * IMPLEMENT/w7h_actions.csv for the firmware-level reverse-engineering this
 * class is based on.
 *
 * `snapshot` (via Go2RTC, no vendor MQTT contract needed), the generic
 * settings/property_set push (shared infra, already proven by the other
 * NextGen devices), `add_water_reset` and the maintenance cycles
 * (drain-and-flush/refill/drain/deep-clean, all `thing.service.start` with a
 * `start_action` code confirmed from live MQTT capture - see startAction())
 * are wired to real MQTT traffic. The remaining commands from w7h_actions.csv
 * (power/stop/lapse/play_sound/...) are known to exist on the device but their
 * exact outbound payload shape was not confirmed, so they are intentionally
 * left unimplemented rather than guessed.
 */
class PetkitEversweetUltra implements DeviceDefinition, Snapshot, BluetoothProxyInterface, HasCamera
{
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
    ];
    protected array $actions = [
        DeviceActions::TAKE_SNAPSHOT,
        DeviceActions::RESET_ADD_WATER,
        DeviceActions::RESET_CUBE,
        DeviceActions::DRAIN_AND_FLUSH,
        DeviceActions::REFILL,
        DeviceActions::DRAIN,
        DeviceActions::DEEP_CLEAN,
    ];

    /**
     * `start_action` values for `thing.service.start`, confirmed from live W7H
     * MQTT capture. Unlike the litter-box firmware, the fountain does not have
     * dedicated reset_cycle_pump/reset_lift_valve RPCs - every maintenance
     * cycle is a `start` with one of these codes.
     */
    private const START_DRAIN_AND_FLUSH = 1;
    private const START_REFILL = 2;
    private const START_DRAIN = 3;
    private const START_DEEP_CLEAN = 4;

    public function __construct(protected Device $device)
    {
    }

    public function subscribedTopics(): array
    {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/connect', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/start', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/ble', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_start/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_over/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_response/post_reply', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public static function deviceName()
    {
        return 'Petkit Eversweet Ultra';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->parseState($device, $message);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                // Unlike the other event topics, property/post carries the device
                // state directly as `params` - not wrapped in a `state` string.
                // `workState` is only present on `params` while a work cycle is
                // running, so its presence/absence directly tracks working/idle.
                $workingState = isset($message->params->workState) && is_object($message->params->workState)
                    ? DeviceStates::WORKING->value
                    : DeviceStates::IDLE->value;

                $this->applyState($device, $message->params, $workingState);
                $this->applyErrorState($device, $message->params);
            },
            sprintf('/sys/%s/%s/thing/event/add_water_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $this->parseState($device, $message);
                $this->recordErrorEvent($device, json_decode($message?->params?->content ?? 'null', false));
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                // parseState() re-syncs `error` from the state's `err` flags via
                // applyErrorState(), which read all-clear on this event - no
                // manual override needed here anymore.
                $this->parseState($device, $message);
                $this->recordErrorEvent($device, json_decode($message?->params?->content ?? 'null', false));
            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $this->parseState($device, $message, DeviceStates::WORKING->value);
            },
            sprintf('/sys/%s/%s/thing/event/pet_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $this->parseState($device, $message, mutate: function (stdClass $state) use ($device) {
                    $state->petDetected = 1;
                    $device->update([
                        'configuration' => $this->updateConfiguration($state)
                    ]);
                    $state->petDetected = 0;
                });
            },
            sprintf('/sys/%s/%s/thing/event/drink_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $this->parseState($device, $message, mutate: function (stdClass $state) use ($device) {
                    $state->drinkDetected = 1;
                    $device->update([
                        'configuration' => $this->updateConfiguration($state)
                    ]);
                    $state->drinkDetected = 0;
                });
            },
        ];
    }

    /**
     * Checks the message for a `state` attribute (present on event topics like
     * add_water_over/error_start/work_start/pet_detect) and, if present,
     * decodes it and applies it exactly like the `property/post` topic does -
     * `state` carries the same device-state shape as `property/post`'s bare
     * params.
     *
     * @param callable(stdClass):void|null $mutate Optional hook applied to the
     *         decoded state after the base update (used for transient detection flags).
     */
    private function parseState(Device $device, ?stdClass $message, string $workingState = DeviceStates::IDLE->value, ?callable $mutate = null): void
    {
        if (!isset($message->params->state)) {
            return;
        }

        $state = json_decode($message->params->state, false);

        if (is_null($state)) {
            return;
        }

        $this->applyState($device, $state, $workingState);
        $this->applyErrorState($device, $state);

        if ($mutate !== null) {
            $mutate($state);
        }
    }

    private function applyState(Device $device, stdClass $state, string $workingState = DeviceStates::IDLE->value): void
    {
        $device->update([
            'working_state' => $workingState,
            'configuration' => $this->updateConfiguration($state)
        ]);
    }

    /**
     * Syncs the `err` fault flags from a decoded state payload onto the device -
     * surfacing the mapped error code, or clearing a previously surfaced error
     * once none of the flags are still set anymore. Called explicitly from
     * every state-bearing topic (property/post directly, everything else via
     * parseState()) so the error field never goes stale.
     */
    private function applyErrorState(Device $device, stdClass $state): void
    {
        $device->update(['error' => $this->prepareErrorReporting($state)]);
    }

    /**
     * Maps IMPLEMENT/w7h_error_states.csv fault flags to a single error code,
     * most severe first. Most of the boolean flags on this shared firmware
     * base are named for litter-box hardware (tary = waste tray, ptc =
     * heater, valve = lift valve, cyc = circulation pump) and are only
     * mapped here where the name unambiguously indicates a fault
     * (…F = full, …O = overflow, …E = error, …M = malfunction).
     * taryO is the exception: on this device it's repurposed to flag the
     * fresh water tank being empty, not a tray overflow.
     */
    private function prepareErrorReporting(stdClass $state): ?string
    {
        // The fault booleans are nested under `err` on the wire, not top-level.
        $err = $state->err ?? null;

        if (($err->taryO ?? null) == 1) {
            return 'water_tank_empty';
        }

        if (($err->taryF ?? null) == 1 || ($state->wtState ?? null) == 2) {
            return 'wastebin_full';
        }

        if (($err->valveE ?? null) == 1) {
            return 'valve_error';
        }

        if (($err->cycM ?? null) == 1) {
            return 'pump_malfunction';
        }

        if (($err->ptcM ?? null) == 1) {
            return 'heater_malfunction';
        }

        if (($err->ptcL ?? null) == 1) {
            return 'heater_low_water';
        }

        return null;
    }

    /**
     * `content` on error_start/error_over (IMPLEMENT/w7h_error_states.csv rows
     * 57-62) carries the error code/message/detail for the error that just
     * became active or just cleared - kept as a "last error" record since
     * neither event's code has a known string table.
     */
    private function recordErrorEvent(Device $device, ?stdClass $content): void
    {
        if ($content === null) {
            return;
        }

        $settings = $device->configuration();
        $settings->lastErrorCode = $content->err ?? null;
        $settings->lastErrorMessage = $content->msg ?? null;
        $settings->lastErrorDetail = $content->detail ?? null;

        $device->update(['configuration' => $settings->toArray()]);
    }

    private function reply(string $topic, ?stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function hasAction(string $action): bool
    {
        return in_array($action, $this->actions);
    }

    public function takeSnapshot(Device $record): void
    {
        TakeSnapshot::dispatchSync($record);
    }

    public function resetAddWater(Device $record): void
    {
        AddWaterReset::dispatchSync($record);
    }

    public function drainAndFlush(Device $record): void
    {
        $this->startAction($record, self::START_DRAIN_AND_FLUSH);
    }

    public function refill(Device $record): void
    {
        $this->startAction($record, self::START_REFILL);
    }

    public function drain(Device $record): void
    {
        $this->startAction($record, self::START_DRAIN);
    }

    public function deepClean(Device $record): void
    {
        $this->startAction($record, self::START_DEEP_CLEAN);
    }

    /**
     * Triggers a maintenance cycle via `thing.service.start`.
     *
     * No confirmed device-reported "in progress" event exists for these cycles
     * (IMPLEMENT/w7h_error_states.csv's flushState/pumpState/liftValveState read
     * 0 in every capture we have, even mid-cycle), so we mark WORKING
     * optimistically here; the next property/post heartbeat resets it to IDLE
     * once the (presumably brief) cycle has finished.
     */
    private function startAction(Device $record, int $startAction): void
    {
        $record->update(['working_state' => DeviceStates::WORKING->value]);
        ServiceStart::dispatchSync($record, $startAction);
    }

    public function resetCube(Device $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->cubeDurability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->cubeNextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray()
        ]);
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration\PetkitEversweetUltra::fromDevice($this->getDevice());
    }

    public function configuration()
    {
        return $this->configurationDefinition()->toArray();
    }

    public function propertyChange(Device $device): void
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

        SetProperty::dispatchSync($device, $difference);
    }

    public function toHomeassistant()
    {
        return json_encode($this->configurationDefinition()->toArray());
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
            case 'snapshot':
                $this->takeSnapshot($this->getDevice());
                break;
            case 'add_water_reset':
                $this->resetAddWater($this->getDevice());
                break;
            case 'drain_and_flush':
                $this->drainAndFlush($this->getDevice());
                break;
            case 'refill':
                $this->refill($this->getDevice());
                break;
            case 'drain':
                $this->drain($this->getDevice());
                break;
            case 'deep_clean':
                $this->deepClean($this->getDevice());
                break;
        }
    }

    public function toOTA(): array
    {
        return [];
    }

    public function toDevSignup(): array
    {
        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'locale' => $this->device->locale,
        ];
    }

    public function toDeviceInfo(): array
    {
        $config = $this->device->configuration['settings'];
        $capacity = $this->device->configuration['capacity'];

        $settings = array_map(
            fn($value) => is_bool($value) ? (int)$value : $value,
            $config
        );

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'signupAt' => $this->device->created_at->format('Y-m-d\TH:i:s.v\+0000'),
            'locale' => $this->device->locale,
            'modelCode' => 0,
            'familyId' => 0,
            'btMac' => $this->device->bt_mac,
            'settings' => $settings,
            'userId' => '1',
            'capacity' => $capacity,
            'cloudProduct' => [],
        ];
    }

    private const FAULT_FLAGS = ['taryD', 'taryL', 'taryF', 'taryO', 'ptcL', 'ptcM', 'valveL', 'valveE', 'valveN', 'cycL', 'cycM', 'repL', 'repM'];
    private const INSTALL_FLAGS = ['stgInstall', 'stgFullState', 'cwtInstall', 'wtInstall', 'wtLock', 'heatInstall'];
    private const RUN_STATE_CODES = ['cameraStatus', 'heatState', 'liftValveState', 'pumpState', 'waterPumpState', 'cwtState', 'wtState', 'addWaterState', 'flushState', 'liftResetState', 'liftLiveState', 'disinfectTime', 'heatLeftTime', 'heatStatusTime', 'heatRealTemp', 'disinfectState'];
    private const HALL_SENSORS = ['hall_CH', 'hall_CL', 'hall_CKL', 'hall_CKR', 'hall_DH', 'hall_DKL', 'hall_DKR', 'hall_LTU', 'hall_LTD', 'hall_TY'];
    private const WORK_STATE_FIELDS = ['workMode', 'workReason', 'safeWarn', 'workProcess'];

    private function updateConfiguration(mixed $content): array
    {
        $settings = $this->getDevice()->configuration();

        //IP - reported inside the `other` string, key/value may or may not be quoted (e.g. Ip:x.x.x.x, Ip:"x.x.x.x" or "Ip":x.x.x.x)
        $pattern = '/"?Ip"?:\\\\?"?(\d{1,3}(?:\.\d{1,3}){3})/';
        $match = Str::of($content->other ?? '')->match($pattern);

        if ($match->value() !== null) {
            $settings->ipAddress = $match->value();
        }

        // Fault flags - IMPLEMENT/w7h_error_states.csv, nested under `err`.
        if (isset($content->err) && is_object($content->err)) {
            foreach (self::FAULT_FLAGS as $flag) {
                if (isset($content->err->$flag)) {
                    $settings->$flag = (bool) $content->err->$flag;
                }
            }
        }

        // Install/lock flags and run-state codes are top-level on the state.
        foreach (self::INSTALL_FLAGS as $flag) {
            if (isset($content->$flag)) {
                $settings->$flag = (bool) $content->$flag;
            }
        }
        foreach (self::RUN_STATE_CODES as $field) {
            if (isset($content->$field)) {
                $settings->$field = (int) $content->$field;
            }
        }
        if (isset($content->addWaterFrequent)) {
            $settings->addWaterFrequent = (bool) $content->addWaterFrequent;
        }

        // Hall-effect sensors, nested under `sensor`.
        if (isset($content->sensor) && is_object($content->sensor)) {
            foreach (self::HALL_SENSORS as $hall) {
                if (isset($content->sensor->$hall)) {
                    $settings->$hall = (float) $content->sensor->$hall;
                }
            }
        }

        // Work-state codes only appear on some events (e.g. work_start), nested under `workState`.
        if (isset($content->workState) && is_object($content->workState)) {
            foreach (self::WORK_STATE_FIELDS as $field) {
                if (isset($content->workState->$field)) {
                    $settings->$field = (int) $content->workState->$field;
                }
            }
        }

        return $settings->toArray();
    }

    public function btConnect(BluetoothDevice $btDevice): void
    {
        ServiceConnect::dispatchSync(
            $this->getDevice(), $btDevice,
        );
    }
}
