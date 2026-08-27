<?php

namespace App\Petkit\Devices\YumshareSolo;

use stdClass;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\EventPublisher;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\ServiceBle;
use App\Jobs\ServiceConnect;
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
use App\Petkit\Devices\Concerns\HandlesFeederSchedule;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class Device implements DeviceDefinition, Snapshot, BluetoothProxyInterface
{
    use HandlesFeederSchedule;

    /** schedule.md §4e: confirmed single-hopper (only 'a', never 'a1'/'a2') at the wire-protocol level. */
    public const FEEDER_COUNT = 1;

    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
    ];
    protected array $actions = [
        DeviceActions::START_FEEDING, DeviceActions::TAKE_SNAPSHOT, DeviceActions::RESET_DESICCANT
    ];

    public function __construct(protected DeviceModel $device)
    {

    }

    public function subscribedTopics(): array
    {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/feed_realtime', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/connect', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/ble', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_start/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_relay_over/post_reply', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/event/ble_response/post_reply', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public static function deviceName()
    {
        return 'Petkit YumShare Solo';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/feed_stop/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/property_post/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/feed_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id ?? null, $message?->params?->content ?? null);

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/eat_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->mergeHistory($message?->params?->event_id, $message?->params?->content);
                EventPublisher::publish($device, 'eat_over');

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/eat_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'EAT',
                        'parameters' => json_decode($message->params->content ?? '{}', true),
                        'device_id' => $device->id,
                    ]);
                    EventPublisher::publish($device, 'eat_start');
                }

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/move_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
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
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/feed_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                $state = json_decode($message?->params?->state, false);

                History::updateOrCreate(['messageId' => $message->params->event_id], [
                    'pet_id' => null,
                    'type' => DeviceStates::WORKING->value,
                    'parameters' => $content,
                    'device_id' => $device->id
                ]);

                $device->update([
                    'working_state' => DeviceStates::WORKING->value,
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            // error_start/error_over do NOT share one event_id the way
            // feed_start/feed_over does - each derives its own event_id
            // from its own timestamp (confirmed via a live D4SH capture:
            // error_start's event_id was ..._1787833502, error_over's own
            // was ..._1787833503, one second later). error_over's content
            // instead carries error_start's timestamp as start_time
            // ({"start_time":<int>,"err":"<string>"}) - reconstruct
            // error_start's messageId from it by swapping this event's own
            // timestamp suffix for that start_time. Distinct from
            // prepareErrorReporting()'s state-embedded flag above - this
            // logs an actual ERROR-type activity entry with a start/end.
            sprintf('/sys/%s/%s/thing/event/error_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content ?? '{}', true) ?? [];

                if (isset($message->params->event_id)) {
                    History::updateOrCreate(['messageId' => $message->params->event_id], [
                        'pet_id' => null,
                        'type' => 'ERROR',
                        'parameters' => ['error' => $content['err'] ?? null],
                        'device_id' => $device->id,
                    ]);
                }

                $device->update(['error' => $content['err'] ?? null]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content ?? '{}', true) ?? [];
                $eventId = $message?->params?->event_id ?? null;
                $startTime = $content['start_time'] ?? null;

                if ($eventId !== null && $startTime !== null && ($pos = strrpos($eventId, '_')) !== false) {
                    $this->mergeHistory(substr($eventId, 0, $pos) . '_' . $startTime, $message?->params?->content ?? null);
                }

                $device->update(['error' => null]);
                $this->reply($topic, $message);
            },
        ];
    }

    /**
     * Merges a follow-up event's content into the History row created for
     * the event it belongs to (found by messageId - the same event_id for
     * start/over pairs like eat_start/eat_over). Silently does nothing if
     * there's no matching row.
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

    private function updateDevice(?stdClass $message)
    {
        $hasError = $message->params->food == 0;
        $isFeeding = $message->params->feeding == 1;
        $err = null;

        if ($hasError) {
            $err = 'food_empty';
        }

        $this->getDevice()->update([
            'working_state' => $isFeeding ? DeviceStates::WORKING->value : DeviceStates::IDLE->value,
            'error' => $err,
        ]);

    }

    public function getDevice(): DeviceModel
    {
        return $this->device;
    }

    public function hasAction(string $action): bool
    {
        $hasAction = in_array($action, $this->actions);

        switch ($action) {
            case DeviceActions::START_FEEDING:
                return $hasAction;
        }

        return $hasAction;
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

    public function resetDesiccant(DeviceModel $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->desiccantDurability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->desiccantNextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray()
        ]);
    }

    public function configuration()
    {
        return $this->configurationDefinition()->toArray();
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
            case 'feed':
                $this->startFeeding($this->getDevice());
                break;
            case 'snapshot':
                $this->takeSnapshot($this->getDevice());
                break;
        }
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
                'foodWarn' => (int)$config['foodWarn'],
                'foodWarnRange' => $config['foodWarnRange'],
                'manualLock' => (int)$config['manualLock'],
                'lightMode' => (int)$config['lightMode'],
                'factor' => $config['factor'],
                'camera' => (int)$config['camera'],
                'microphone' => (int)$config['microphone'],
                'night' => (int)$config['night'],
                'timeDisplay' => (int)$config['timeDisplay'],
                'feedPicture' => (int)$config['feedPicture'],
                'eatVideo' => (int)$config['eatVideo'],
                'moveDetection' => (int)$config['moveDetection'],
                'moveSensitivity' => (int)$config['moveSensitivity'],
                'petDetection' => (int)$config['petDetection'],
                'petSensitivity' => (int)$config['petSensitivity'],
                'eatDetection' => (int)$config['eatDetection'],
                'eatSensitivity' => (int)$config['eatSensitivity'],
                'detectInterval' => (int)$config['detectInterval'],
                'toneMode' => (int)$config['toneMode'],
                'soundEnable' => (int)$config['soundEnable'],
                'systemSoundEnable' => (int)$config['systemSoundEnable'],
                'volume' => (int)$config['volume'],
                'selectedSound' => (int)$config['selectedSound'],
                'numLimit' => (int)$config['numLimit'],
                'surplusControl' => (int)$config['surplusControl'],
                'surplusStandard' => (int)$config['surplusStandard'],
                'smartFrame' => (int)$config['smartFrame'],
                'upload' => (int)$config['upload'],
                'attire' => $config['attire'],
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

    private function updateConfiguration(mixed $content): array
    {
        $settings = $this->getDevice()->configuration();

        //IP - reported inside the `other` string, key/value may or may not be quoted (e.g. Ip:"x.x.x.x" or "Ip":x.x.x.x)
        $pattern = '/(?:^|,)Ip:\\\\?"(\d{1,3}(?:\.\d{1,3}){3})\\\\?"/';
        $match = Str::of($content->other)->match($pattern);

        if ($match->value() !== null) {
            $settings->ipAddress = $match->value();
        }

        $settings->ipAddress = $match->value();
        $settings->infrared = $content->ir;
        $settings->bowl = $content->bowl;
        $settings->door = $content->door;

        return $settings->toArray();
    }

    private function prepareErrorReporting(mixed $state)
    {
        $error = null;

        if ($state?->food == 0) {
            $error = 'food_empty';
        }

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
