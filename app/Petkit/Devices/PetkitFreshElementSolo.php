<?php

namespace App\Petkit\Devices;

use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\HomeassistantTopic;
use App\Jobs\FeedRealtime;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Models\Device;
use App\Models\History;
use App\Models\Pet;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class PetkitFreshElementSolo implements DeviceDefinition
{
    protected array $actions = [
        DeviceActions::START_FEEDING
    ];
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
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

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/feed_stop/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

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

                $device->update([
                    'working_state' => DeviceStates::WORKING->value
                ]);

                $this->reply($topic, $message);

            },
            sprintf('/ota/device/inform/%s/%s', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $message = OtaMessage::send($device);
                MQTT::connection('publisher')->publish($message->getTopic(), $message->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/data_get/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);
                $msg = UserGet::reply($device->productKey(), $device->deviceName(), $message);
                MQTT::connection('publisher')->publish($msg->getTopic(), $msg->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);
                $this->updateDevice($message);
                $msg = UserGet::replyToState($device->productKey(), $device->deviceName(), $message);
                MQTT::connection('publisher')->publish($msg->getTopic(), $msg->getMessage());
            }
        ];
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

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    public function startFeeding(Device $record): void
    {
        FeedRealtime::dispatchSync($record, $this->device->configuration['settings']['amount'] ?? 10);
        ServiceStart::dispatchSync($record, $this->device->configuration['settings']['amount'] ?? 10);
    }
    public static function deviceName()
    {
        return 'Petkit FreshElement Solo';
    }

    public function defaultConfiguration()
    {
        return $this->configurationDefinition()->toArray();
    }

    public function propertyChange(Device $device): void
    {
        $scheduleChange = false;
        $difference = JsonHelper::difference($device->configuration['settings'], $device->getOriginal('configuration')['settings']);
        if(empty($difference)) {
            $difference = JsonHelper::difference($device->configuration['schedule'], $device->getOriginal('configuration')['schedule']);
            $scheduleChange = !empty($difference);
        }

        if(!$scheduleChange) {
            foreach ($difference as $key => $value) {
                if (is_numeric($value) || is_bool($value) ) {
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

    public function toHomeassistant()
    {
        return json_encode($this->configurationDefinition()->toArray());
    }

    public function configurationDefinition(): ConfigurationInterface {
        return new Configuration\PetkitFreshElementSolo($this->getDevice());
    }

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(\stdClass $message) {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach($keys as $attributeName => $value) {
            $methodName = 'set' . ucfirst($attributeName);
            $configuration->$methodName($value);
        }

        $deviceConfig = $configuration->toArray();

        $update = $this->getDevice()->update(['configuration' => $deviceConfig]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(\stdClass $message): void {
        $action = $message->action;
        switch ($action) {
            case 'feed':
                $this->startFeeding($this->getDevice());
                break;
        }
    }

    public function toOTA(): array
    {
        return [
            'firmwareId' => 20,
            'version' => '1.262',
            'details' => [
                [
                    'id' => 24,
                    'module' => 'userbin',
                    'version' => 2344001,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/D4/1.262/fec15b9a-59e9-4966-864d-74bbaa6ba7fd.bin',
                        'size' => 1362832,
                        'digest' => '21e76de8fa692a5863730e454904f2c9'
                    ]
                ]
            ]
        ];
    }

    public function toDevSignup(): array {
        return $this->toDeviceInfo();
    }

    public function toDeviceInfo(): array {
        $config = $this->device->configuration['settings'];

        return [
            'btMac' => $this->device->bt_mac,
            'id' => $this->device->petkit_id,
            'locale' => $this->device->locale,
            'mac' => $this->device->mac,
            'multiConfig' => (int)$config['multiConfig'],
            'secret' => $this->device->secret ?? '',
            'settings' => [
                'factor' => (int)$config['factor'],
                'feedSound' => (int)$config['feedSound'],
                'foodWarn' => (int)$config['foodWarn'],
                'foodWarnRange' => $config['foodWarnRange'],
                'lightMode' => (int)$config['lightMode'],
                'lightRange' => $config['lightRange'],
                'manualLock' => (int)$config['manualLock'],
            ],
            'shareOpen' => $config['shareOpen'],
            'signupAt' => Carbon::now()->format('Y-m-d\TH:i:s.v\Z'),
            'sn' => $this->device->serial_number,
            'timezone' => $this->device->timezone,
            'typeCode' => $config['typeCode'] ?? 1,
            'userId' => "1"
        ];
    }

    private function updateDevice(?\stdClass $message)
    {
        $hasError = $message->params->food == 0;
        $isFeeding = $message->params->feeding == 1;
        $err = null;

        if($hasError) {
            $err = 'food_empty';
        }

        $this->getDevice()->update([
            'working_state' => $isFeeding ? DeviceStates::WORKING->value : DeviceStates::IDLE->value,
            'error' => $err,
        ]);

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
}
