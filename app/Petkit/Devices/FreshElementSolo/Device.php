<?php

namespace App\Petkit\Devices\FreshElementSolo;

use stdClass;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\HomeassistantTopic;
use App\Jobs\FeedRealtime;
use App\Jobs\ServiceBle;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Models\BluetoothDevice;
use App\Models\Device as DeviceModel;
use App\Models\History;
use App\Models\Pet;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\BluetoothDevices\BluetoothProxyInterface;
use App\Petkit\BluetoothDevices\Message;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\DeviceStates;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class Device implements DeviceDefinition, BluetoothProxyInterface
{
    protected array $actions = [
        DeviceActions::START_FEEDING,
        DeviceActions::RESET_DESICCANT,
    ];
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
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
        ];
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
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/feed_over/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/feed_start/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {

                $content = json_decode($message?->params?->content, false);

                History::updateOrCreate(['messageId' => $message->params->event_id], [
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
            sprintf('/ota/device/inform/%s/%s', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $message = OtaMessage::send($device);
                MQTT::connection('publisher')->publish($message->getTopic(), $message->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/data_get/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->reply($topic, $message);
                $msg = UserGet::reply($device->productKey(), $device->deviceName(), $message);
                MQTT::connection('publisher')->publish($msg->getTopic(), $msg->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (DeviceModel $device, string $topic, stdClass|null $message) {
                $this->reply($topic, $message);
                $this->updateDevice($message);
                $msg = UserGet::replyToState($device->productKey(), $device->deviceName(), $message);
                MQTT::connection('publisher')->publish($msg->getTopic(), $msg->getMessage());
            }
        ];
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

    private function reply(string $topic, ?stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    public function startFeeding(DeviceModel $record, ?int $amount = null): void
    {
        $amount ??= $this->device->configuration['settings']['amount'] ?? 10;

        FeedRealtime::dispatchSync($record, $amount);
        ServiceStart::dispatchSync($record, $amount);
    }
    public static function deviceName()
    {
        return 'Petkit FreshElement Solo';
    }

    public function configuration()
    {
        return $this->configurationDefinition()->toArray();
    }

    /**
     * Settings and schedule can both change in the same save (e.g. toggling
     * sche_enable while also editing feed times) - the device takes those
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

    public function configurationDefinition(): ConfigurationInterface {
        return Configuration::fromDevice($this->getDevice());
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

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(stdClass $message) {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach($keys as $attributeName => $value) {
            $configuration->$attributeName = $value;
        }
        $this->getDevice()->update(['configuration' => $configuration]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(stdClass $message): void {
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
                'foodWarnRange' => [$config['foodWarnRange']['from'], $config['foodWarnRange']['till']],
                'lightMode' => (int)$config['lightMode'],
                'lightRange' => [$config['lightRange']['from'], $config['lightRange']['till']],
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

    private function updateDevice(?stdClass $message)
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

    public function toFeed(DeviceModel $device): string
    {
        $latest = Time::calculateLatest($device->configuration['schedule']);
        $nextTick = last($latest) ?: ['a' => 0, 'id' => '', 't' => 0];

        return json_encode([
            'schedule' => array_map(fn(array $schedule) => [
                ...$schedule,
                're' => Time::toWireRepeatDays($schedule['re']),
            ], $device->configuration['schedule']),
            'nextTick' => $nextTick['t'],
            'latest' => $latest
        ]);
    }

    public function btConnect(BluetoothDevice $btDevice): void
    {
        ServiceConnect::dispatchSync(
            $this->getDevice(), $btDevice
        );

    }

    public function btWrite(BluetoothDevice $btDevice, string $commandBase64, int $cmd): void
    {
        ServiceBle::dispatchSync(
            $this->getDevice(), $btDevice, $commandBase64, $cmd,
        );
    }
}
