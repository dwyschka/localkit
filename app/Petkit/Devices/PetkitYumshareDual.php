<?php

namespace App\Petkit\Devices;

use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\FeedRealtime;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Jobs\TakeSnapshot;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Models\History;
use App\MQTT\GenericReply;
use App\Petkit\BluetoothDevices\BluetoothProxyInterface;
use App\Petkit\BluetoothDevices\Message;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class PetkitYumshareDual implements DeviceDefinition, Snapshot, BluetoothProxyInterface
{
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
    ];
    protected array $actions = [
        DeviceActions::START_FEEDING, DeviceActions::TAKE_SNAPSHOT, DeviceActions::RESET_DESICCANT
    ];

    public function __construct(protected Device $device)
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
        return 'Petkit YumShare Dual';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->parseState($device, $message);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/feed_stop/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/property_post/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/feed_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/eat_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/eat_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
            },
            sprintf('/sys/%s/%s/thing/event/move_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message, mutate: function (\stdClass $state) use ($device) {
                    $state->moveDetected = 1;
                    $device->update([
                        'configuration' => $this->updateConfiguration($state)
                    ]);
                    $state->moveDetected = 0;
                });
            },
            sprintf('/sys/%s/%s/thing/event/pet_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message, mutate: function (\stdClass $state) use ($device) {
                    $state->petDetected = 1;
                    $device->update([
                        'configuration' => $this->updateConfiguration($state)
                    ]);
                    $state->petDetected = 0;
                });
            },
            sprintf('/sys/%s/%s/thing/event/feed_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);

                History::create([
                    'messageId' => $message->params->event_id,
                    'pet_id' => null,
                    'type' => DeviceStates::WORKING->value,
                    'parameters' => $content,
                    'device_id' => $device->id
                ]);

                $this->parseState($device, $message, DeviceStates::WORKING->value);
            },
        ];
    }

    /**
     * Checks the message for a `state` attribute and, if present, parses it the
     * same way the property_post topic does: updating the working state, error
     * reporting and the stored configuration.
     *
     * @param  (callable(\stdClass): void)|null  $mutate  Optional hook applied to the
     *         decoded state after the base update (used for transient detection flags).
     */
    private function parseState(Device $device, ?\stdClass $message, string $workingState = DeviceStates::IDLE->value, ?callable $mutate = null): void
    {
        if (!isset($message->params->state)) {
            return;
        }

        $state = json_decode($message->params->state, false);

        if (is_null($state)) {
            return;
        }

        $device->update([
            'working_state' => $workingState,
            'error' => $this->prepareErrorReporting($state),
            'configuration' => $this->updateConfiguration($state)
        ]);

        if ($mutate !== null) {
            $mutate($state);
        }
    }

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    private function updateDevice(?\stdClass $message)
    {
        $hasError = $message->params->food1 == 0 || $message->params->food2 == 0;
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

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function hasAction(string $action): bool
    {
        $hasAction = in_array($action, $this->actions);
        if ($this->device->proxy_mode == 1) {
            return false;
        }
        switch ($action) {
            case DeviceActions::START_FEEDING:
                return $hasAction;
        }

        return $hasAction;
    }

    public function takeSnapshot(Device $record): void
    {
        TakeSnapshot::dispatchSync($record);
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration\PetkitYumshareDual::fromDevice($this->getDevice());
    }

    public function resetDesiccant(Device $record): void
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

    public function propertyChange(Device $device): void
    {
        $scheduleChange = false;
        $difference = JsonHelper::difference($device->configuration['settings'], $device->getOriginal('configuration')['settings']);
        if (empty($difference)) {
            $difference = JsonHelper::difference($device->configuration['schedule'], $device->getOriginal('configuration')['schedule']);
            $scheduleChange = !empty($difference);
        }

        $dto = $this->configurationDefinition();


        if (!$scheduleChange) {
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
            SetProperty::dispatchSync($device, $difference);
        } else {

            SetProperty::dispatchSync($device, [
                'feed' => $this->toFeed($device)
            ]);
        }

    }

    public function toFeed(Device $device): string
    {

        $latest = Time::calculateLatest($device->configuration['schedule']);
        $nextTick = last($latest);

        return json_encode([
            'schedule' => $device->configuration['schedule'],
            'nextTick' => $nextTick['t'],
            'latest' => $latest
        ]);


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
    public function settings(\stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach($keys as $attributeName => $value) {
            $configuration->$attributeName = $value;
        }
        $this->getDevice()->update(['configuration' => $configuration]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(\stdClass $message): void
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

    public function startFeeding(Device $record, ?int $amount = null, ?int $amount2 = null): void
    {
        $settings = $this->device->configuration['settings'];
        $amount ??= $settings['amount1'] ?? 1;
        $amount2 ??= $settings['amount2'] ?? 1;

        // Dual is a two-hopper feeder, so feed_realtime carries amount1/amount2.
        FeedRealtime::dispatchSync($record, $amount, $amount2);
        ServiceStart::dispatchSync($record, $amount + $amount2);
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
                'timeDisplay' => (int)$config['timeDisplay'],
                'night' => (int)$config['night'],
                'microphone' => (int)$config['microphone'],
                'factor1' => (int)$config['factor1'],
                'factor2' => (int)$config['factor2'],
                'foodWarn' => (int)$config['foodWarn'],
                'foodWarnRange' => $config['foodWarnRange'],
                'lightMode' => (int)$config['lightMode'],
                'lightMultiRange' => $config['lightMultiRange'],
                'toneMode' => (int)$config['toneMode'],
                'toneMultiRange' => $config['toneMultiRange'],
                'manualLock' => (int)$config['manualLock'],
                'sche_enable' => (int)$config['sche_enable'],
                'CTime' => (int)$config['CTime'],
                'camera' => (int)$config['camera'],
                'cameraMultiRange' => $config['cameraMultiRange'],
                'cameraRangeTable' => $config['cameraRangeTable'],
                'moveDetection' => (int)$config['moveDetection'],
                'moveSensitivity' => (int)$config['moveSensitivity'],
                'petDetection' => (int)$config['petDetection'],
                'petSensitivity' => (int)$config['petSensitivity'],
                'eatDetection' => (int)$config['eatDetection'],
                'eatSensitivity' => (int)$config['eatSensitivity'],
                'detectInterval' => (int)$config['detectInterval'],
                'detectMultiRange' => $config['detectMultiRange'],
                'feedPicture' => (int)$config['feedPicture'],
                'eatVideo' => (int)$config['eatVideo'],
                'soundEnable' => (int)$config['soundEnable'],
                'systemSoundEnable' => (int)$config['systemSoundEnable'],
                'feedSound' => (int)$config['feedSound'],
                'volume' => (int)$config['volume'],
                'selectedSound' => (int)$config['selectedSound'],
                'surplusControl' => (int)$config['surplusControl'],
                'surplusStandard' => (int)$config['surplusStandard'],
                'smartFrame' => (int)$config['smartFrame'],
                'upload' => (int)$config['upload'],
                'attireId' => (int)$config['attireId'],
                'logo_cn' => (int)$config['logo_cn'],
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
        $latest = Time::calculateLatest($this->device->configuration['schedule']);
        $nextTick = last($latest);
        $schedules = $this->device->configuration['schedule'];

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


        return [
            'schedule' => $schedules,
            'nextTick' => $nextTick['t'],
            'latest' => $latest
        ];
    }

    private function updateConfiguration(mixed $content): array
    {
        $settings = $this->getDevice()->configuration();

        //IP - reported inside the `other` string, key/value may or may not be quoted (e.g. Ip:x.x.x.x, Ip:"x.x.x.x" or "Ip":x.x.x.x)
        $pattern = '/"?Ip"?:\\\\?"?(\d{1,3}(?:\.\d{1,3}){3})/';
        $match = Str::of($content->other)->match($pattern);

        if ($match->value() !== null) {
            $settings->ipAddress = $match->value();
        }

        $settings->bowl = $content->bowl;
        $settings->door = $content->door;
        $settings->eatDetected = $content->eating;

        return $settings->toArray();
    }

    private function prepareErrorReporting(mixed $state)
    {
        $error = null;

        if (($state?->food1 ?? 1) == 0 || ($state?->food2 ?? 1) == 0) {
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
}
