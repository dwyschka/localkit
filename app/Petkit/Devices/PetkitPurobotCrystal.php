<?php

namespace App\Petkit\Devices;

use stdClass;
use Throwable;
use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Helpers\Time;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\ServiceConnect;
use App\Jobs\ServiceEnd;
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
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class PetkitPurobotCrystal implements DeviceDefinition, Snapshot, BluetoothProxyInterface
{
    public static $workingStates = [
        DeviceStates::CLEANING, DeviceStates::IDLE, DeviceStates::PET_IN,
    ];
    protected array $actions = [
        DeviceActions::START_CLEAN, DeviceActions::DEODORIZE, DeviceActions::LEVEL, DeviceActions::TAKE_SNAPSHOT,
        DeviceActions::RESET_N60, DeviceActions::RESET_CARDBOARD, DeviceActions::RESET_WORKING_STATE,
        DeviceActions::START_LIGHTNING, DeviceActions::STOP_LIGHTNING,
    ];

    public function __construct(protected Device $device)
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
        return 'Purobot Crystal';
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/ble_response/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $content = json_decode($message?->params?->content, false);
                Message::handleProxyMessage($content);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
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
            sprintf('/sys/%s/%s/thing/event/move_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $state->moveDetected = 1;

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);

                $state->moveDetected = 0;
                $device->update([
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/pet_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {

                $state = json_decode($message?->params?->state, false);
                $state->petDetected = 1;

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);

                $state->petDetected = 0;
                $device->update([
                    'configuration' => $this->updateConfiguration($state)
                ]);

            },
            sprintf('/sys/%s/%s/thing/event/pet_in/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $configuration = $this->configurationDefinition();
                $configuration->petDetected = true;

                $device->update([
                    'configuration' => $configuration->toArray()
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $configuration = $this->configurationDefinition();
                $configuration->petDetected = false;

                $device->update([
                    'configuration' => $configuration->toArray()
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/light_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $conf =  $this->updateConfiguration($state, ['lightning' => isset($state->lightState)]);

                // The light lifecycle is driven entirely through this topic: when the light is
                // running the state carries a `lightState` object, and it's absent once it stops.
                $device->update([
                    'configuration' => $conf
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, stdClass|null $message) {
                $state = json_decode($message?->params?->state, false);

                $device->update([
                    'error' => $this->prepareErrorReporting($state),
                    'configuration' => $this->updateConfiguration($state)
                ]);
            },
        ];
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
        $hasAction = in_array($action, $this->actions);

        switch ($action) {
            case DeviceActions::START_CLEAN:
            case DeviceActions::DEODORIZE:
            case DeviceActions::LEVEL:
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::START_LIGHTNING:
                return $hasAction && !($this->device->configuration['states']['lightning'] ?? false);

            case DeviceActions::STOP_LIGHTNING:
                return $hasAction && ($this->device->configuration['states']['lightning'] ?? false);
        }

        return $hasAction;
    }

    public function takeSnapshot(Device $record): void
    {
        TakeSnapshot::dispatchSync($record);
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration\PetkitPurobotCrystal::fromDevice($this->getDevice());
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
        $data = $this->configurationDefinition()->toArray();

        foreach (['moveDetected', 'petDetected', 'lightning'] as $state) {
            if (array_key_exists($state, $data['states'])) {
                $data['states'][$state] = (int)$data['states'][$state];
            }
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
            case 'reset_n60':
                $this->resetN60($this->getDevice());
                break;
            case 'reset_cardboard':
                $this->resetCardboard($this->getDevice());
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
        }
    }

    public function resetN60(Device $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->n60Durability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->n60NextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray()
        ]);
    }

    public function resetCardboard(Device $record): void
    {
        $configuration = $this->configurationDefinition();
        $durability = $configuration->cardboardDurability;
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration->cardboardNextChange = $nextChange->timestamp;

        $record->update([
            'configuration' => $configuration->toArray()
        ]);
    }

    public function resetWorkingState(Device $record): void
    {
        $configuration = $this->configurationDefinition();
        $configuration->lightning = false;

        $record->update([
            'working_state' => DeviceStates::IDLE->value,
            'configuration' => $configuration->toArray()
        ]);
    }

    public function startCleaning(Device $record): void
    {
        ServiceStart::dispatchSync($record, 0);
    }

    public function deodorize(Device $record): void
    {
        ServiceStart::dispatchSync($record, 2);
    }

    public function level(Device $record): void
    {
        ServiceStart::dispatchSync($record, 4);
    }

    public function startLightning(Device $record): void
    {
        ServiceStart::dispatchSync($record, 7);
    }

    public function stopLightning(Device $record): void
    {
        ServiceEnd::dispatchSync($record, 7);
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
                'lightMode' => (int)$config['lightMode'],
                'timeDisplay' => (int)$config['timeDisplay'],
                'camera' => (int)$config['camera'],
                'microphone' => (int)$config['microphone'],
                'night' => (int)$config['night'],
                'microlight' => (int)$config['microlight'],
                'cameraLight' => (int)$config['cameraLight'],
                'preLive' => (int)$config['preLive'],
                'lightAssist' => (int)$config['lightAssist'],
                'toiletLightAssist' => (int)$config['toiletLightAssist'],
                'wifiLightAssist' => (int)$config['wifiLightAssist'],
                'moveDetection' => (int)$config['moveDetection'],
                'moveSensitivity' => (int)$config['moveSensitivity'],
                'petDetection' => (int)$config['petDetection'],
                'toiletDetection' => (int)$config['toiletDetection'],
                'detectInterval' => (int)$config['detectInterval'],
                'autoWork' => (int)$config['autoWork'],
                'autoIntervalMin' => (int)$config['autoIntervalMin'],
                'fixedTimeClear' => (int)$config['fixedTimeClear'],
                'avoidRepeat' => (int)$config['avoidRepeat'],
                'underweight' => (int)$config['underweight'],
                'kitten' => (int)$config['kitten'],
                'bury' => (int)$config['bury'],
                'deepClean' => (int)$config['deepClean'],
                'downpos' => (int)$config['downpos'],
                'sandType' => (int)$config['sandType'],
                'unit' => (int)$config['unit'],
                'stillTime' => (int)$config['stillTime'],
                'lightest' => (int)$config['lightest'],
                'sandTrayStandardDay' => (int)$config['sandTrayStandardDay'],
                'sandTrayStandardDayMax' => (int)$config['sandTrayStandardDayMax'],
                'urine' => (int)$config['urine'],
                'softMode' => (int)$config['softMode'],
                'softModeClean' => (int)$config['softModeClean'],
                'occult' => (int)$config['occult'],
                'disturbMode' => (int)$config['disturbMode'],
                'toneMode' => (int)$config['toneMode'],
                'soundEnable' => (int)$config['soundEnable'],
                'systemSoundEnable' => (int)$config['systemSoundEnable'],
                'volume' => (int)$config['volume'],
                'selectedSound' => (int)$config['selectedSound'],
                'voice' => (int)$config['voice'],
                'upload' => (int)$config['upload'],
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
}
