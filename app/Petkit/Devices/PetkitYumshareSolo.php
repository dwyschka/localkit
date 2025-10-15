<?php

namespace App\Petkit\Devices;

use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\HomeassistantTopic;
use App\Jobs\FeedRealtime;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Jobs\TakeSnapshot;
use App\Models\Device;
use App\Models\History;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class PetkitYumshareSolo implements DeviceDefinition
{
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
    ];
    protected array $actions = [
        DeviceActions::START_FEEDING, DeviceActions::TAKE_SNAPSHOT
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
        ];
    }

    public static function deviceName()
    {
        return 'Petkit YumShare Solo';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/feed_stop/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/property_post/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/feed_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/eat_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/eat_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/move_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/pet_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/feed_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                $state = json_decode($message?->params?->state, false);

                History::create([
                    'messageId' => $message->params->event_id,
                    'pet_id' => null,
                    'type' => DeviceStates::WORKING->value,
                    'parameters' => $content,
                    'device_id' => $device->id
                ]);

                $device->update([
                    'working_state' => DeviceStates::WORKING->value,
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
        ];
    }

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    private function updateDevice(?\stdClass $message)
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
        return new Configuration\PetkitYumshareSolo($this->getDevice());
    }

    public function defaultConfiguration()
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

        if (!$scheduleChange) {
            foreach ($difference as $key => $value) {
                if (is_numeric($value) || is_bool($value)) {
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

    private function toFeed(Device $device): string
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

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(\stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach ($keys as $attributeName => $value) {
            $methodName = 'set' . ucfirst($attributeName);
            $configuration->$methodName($value);
        }

        $deviceConfig = $configuration->toArray();

        $update = $this->getDevice()->update(['configuration' => $deviceConfig]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(\stdClass $message): void
    {
        $action = $message->action;
        switch ($action) {
            case 'feed':
                $this->startFeeding($this->getDevice());
                break;
        }
    }

    public function startFeeding(Device $record): void
    {
        FeedRealtime::dispatchSync($record, $this->device->configuration['settings']['amount'] ?? 10);
        ServiceStart::dispatchSync($record, $this->device->configuration['settings']['amount'] ?? 10);
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
            'userId' => '200066438',
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
        $settings = $this->device->settings;

        Log::info('update configuration', ['content' => $content]);
        //IP
        $pattern = '/Ip:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';
        $match = Str::of($content->other)->match($pattern);


        $settings['states']['ipAddress'] = $match->value();

        return $settings;
    }
}
