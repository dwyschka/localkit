<?php

namespace App\Petkit\Devices;

use App\Helpers\JsonHelper;
use App\Homeassistant\HomeassistantTopic;
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

class PetkitPuraMax implements DeviceDefinition
{
    protected array $actions = [
        DeviceActions::START_CLEAN,
        DeviceActions::START_MAINTENANCE,
        DeviceActions::STOP_MAINTENANCE,
        DeviceActions::CLEAN_LITTER,
        DeviceActions::START_ODOUR,
        DeviceActions::START_LIGHTNING,
        DeviceActions::STOP_LIGHTNING,
        DeviceActions::RESET_N50,
    ];
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE, DeviceStates::PET_IN, DeviceStates::CLEANING, DeviceStates::MAINTENANCE,
    ];

    public function __construct(protected Device $device)
    {

    }

    public function subscribedTopics(): array
    {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/end', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/start', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public function stateTopics(): array
    {
        return [
            sprintf('/sys/%s/%s/thing/event/work_continue/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $content = json_decode($message?->params?->content, false);
                $deviceStatus = $this->deviceStatus($content?->action);

                $device->update([
                    'working_state' => $deviceStatus
                ]);

                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/work_suspend/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $content = json_decode($message?->params?->content, false);
                $deviceStatus = $this->deviceStatus($content?->action);

                if ($deviceStatus !== DeviceStates::IDLE->value) {
                    History::create([
                        'messageId' => $message->params->event_id,
                        'pet_id' => null,
                        'type' => $deviceStatus,
                        'parameters' => $content,
                        'device_id' => $device->id
                    ]);
                }

                $device->update([
                    'working_state' => $deviceStatus
                ]);

                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/dump_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/reset_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->updateLitter($device, $message);
                $this->updateHistory($message);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/pet_in/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::PET_IN->value
                ]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value
                ]);
                $this->reply($topic, $message);

                $content = json_decode($message->params->content, true);
                History::create([
                    'messageId' => $message->params->event_id,
                    'pet_id' => Pet::nearestWeight($content['pet_weight']) ?? null,
                    'parameters' => $content,
                    'type' => 'IN_USE',
                    'device_id' => $device->id
                ]);
            },
            sprintf('/sys/%s/%s/thing/event/error_start/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {

                $msg = $message->params->content;
                $msg = json_decode($msg, false);

                $device->update([
                    'error' => $msg->err
                ]);

                History::create([
                    'messageId' => 'custom-err-' . now()->timestamp,
                    'pet_id' => null,
                    'type' => 'ERROR',
                    'parameters' => [
                        'error' => $msg->err,
                    ],
                    'device_id' => $device->id,
                ]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $device->update([
                    'working_state' => DeviceStates::IDLE->value,
                    'error' => null
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

                if (!empty($message?->params?->litter)) {
                    $configuration = $device->configuration;
                    $configuration['litter'] = (array)$message->params->litter;
                    $device->update(['configuration' => $configuration]);
                }

                if (!empty($message?->params?->battery)) {
                    $configuration = $device->configuration;
                    $configuration['consumables']['k3_battery'] = (int)$message->params->battery;
                    $device->update(['configuration' => $configuration]);
                }

                if (!empty($message?->params?->liquid)) {
                    $configuration = $device->configuration;
                    $configuration['consumables']['k3_liquid'] = (int)$message->params->liquid;
                    $device->update(['configuration' => $configuration]);
                }

                if (!isset($message?->params?->work_state)) {
                    $device->update(['working_state' => DeviceStates::IDLE->value]);
                } else {
                    $deviceStatus = $this->deviceStatus($message->params->work_state->work_mode);
                    $device->update(['working_state' => $deviceStatus]);
                }

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
        $hasK3 = !empty($this->device->configuration['k3Device']);
        if ($this->device->proxy_mode == 1) {
            return false;
        }
        switch ($action) {
            case DeviceActions::RESET_N50:
                return $hasAction;

            case DeviceActions::CLEAN_LITTER:
            case DeviceActions::START_MAINTENANCE:
            case DeviceActions::START_CLEAN:
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::START_ODOUR:
            case DeviceActions::START_LIGHTNING:
            case DeviceActions::STOP_LIGHTNING:
                return $hasAction && $hasK3;

            case DeviceActions::STOP_MAINTENANCE:
                return $hasAction && $this?->device?->working_state == DeviceStates::MAINTENANCE->value;
        }

        return $hasAction;
    }

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::connection('publisher')->publish($generic->getTopic(), $generic->getMessage());
    }

    public function startCleaning(Device $record)
    {
        ServiceStart::dispatchSync($record, 0);
    }

    public function startMaintenance(Device $record)
    {
        ServiceStart::dispatchSync($record, 9);
    }

    public function stopMaintenance(Device $record)
    {
        ServiceEnd::dispatchSync($record, 9);
    }

    public function cleanLitter(Device $record)
    {
        ServiceStart::dispatchSync($record, 1);
    }

    public function startOdour(Device $record)
    {
        ServiceStart::dispatchSync($record, 2);
    }

    public function startLightning(Device $record)
    {
        ServiceStart::dispatchSync($record, 7);
    }

    public function stopLightning(Device $record)
    {
        ServiceStart::dispatchSync($record, 7);
    }

    public function resetN50(Device $record) {
        $consumables = $record->configuration['consumables'] ?? $record->definition()->configuration()['consumables'];
        $durability = $consumables['n50_durability'];
        $nextChange = Carbon::now()->addDays((int)$durability);

        $configuration = $record->configuration;
        $configuration['consumables'] = [
            'n50_durability' => $durability,
            'n50_next_change' => $nextChange->timestamp
        ];

        $record->update([
            'configuration' => $configuration
        ]);
    }


    public static function deviceName()
    {
        return 'Petkit Pura Max';
    }

    public function configuration()
    {
        return $this->configurationDefinition()->toArray();
    }

    public function propertyChange(Device $device): void
    {
        $difference = JsonHelper::difference($device->configuration['settings'], $device->getOriginal('configuration')['settings']);
        foreach ($difference as $key => $value) {
            if (is_numeric($value)) {
                $difference[$key] = (int)$value;
            } else if (is_bool($value)) {
                $difference[$key] = (int)$value;
            }
        }
        SetProperty::dispatchSync($device, $difference);
    }


    private function deviceStatus($parameter): string
    {
        $deviceStatus = DeviceStates::IDLE->value;
        switch ($parameter) {

            case 0:
                $deviceStatus = DeviceStates::CLEANING->value;
                break;
            case 9:
                $deviceStatus = DeviceStates::MAINTENANCE->value;
                break;
        }
        return $deviceStatus;
    }

    private function updateHistory(\stdClass|null $message)
    {
        if (is_null($message)) {
            return;
        }
        $content = json_decode($message->params->content, true);
        $history = History::where('messageId', $message->params->event_id)->first();
        if (!is_null($history)) {
            $history->update([
                'parameters' => [
                    ...$history->parameters,
                    ...$content
                ]
            ]);
        }
    }


    public function toHomeassistant()
    {
        return json_encode($this->configurationDefinition()->toArray());
    }

    public function configurationDefinition(): ConfigurationInterface {
        return \App\Petkit\Devices\Configuration\PetkitPuraMax::fromDevice($this->getDevice());
    }

    #[HomeassistantTopic(topic: 'setting/set')]
    public function settings(\stdClass $message) {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach($keys as $attributeName => $value) {
            $methodName = 'set' . ucfirst($attributeName);
            $configuration->$methodName($value);
        }

        $deviceConfig = Arr::mergeRecursiveDistinct($configuration->toArray(), $this->getDevice()->configuration ?? []);
        $this->getDevice()->update(['configuration' => $deviceConfig]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(\stdClass $message): void {
        $action = $message->action;
        switch ($action) {
            case 'start_maintenance':
                $this->startMaintenance($this->getDevice());
                break;
            case 'start_cleaning':
                $this->startCleaning($this->getDevice());
                break;
            case 'start_lightning':
                $this->startLightning($this->getDevice());
                break;
            case 'start_odour':
                $this->startOdour($this->getDevice());
                break;
            case 'stop_maintenance':
                $this->stopMaintenance($this->getDevice());
                break;
            case 'stop_lightning':
                $this->stopLightning($this->getDevice());
                break;
            case 'clean_litter':
                $this->cleanLitter($this->getDevice());
                break;
            case 'reset_n50':
                $this->resetN50($this->getDevice());
                break;
            default:
                Log::error('Unknown action: ' . $action);
        }
    }

    private function updateLitter(Device $device, ?\stdClass $message)
    {
        if (is_null($message)) {
            return;
        }
        $state = json_decode($message->params->state, false);
        $configuration = $device->configuration;
        $configuration['litter'] = (array)$state->litter;
        $device->update(['configuration' => $configuration]);
    }


    public function toOTA(): array
    {
        return [
            'firmwareId' => 33,
            'version' => '1.625',
            'details' => [
                [
                    'id' => 50,
                    'module' => 'userbin',
                    'version' => 2447004,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/63ab5fc6-38c0-4333-ad5b-24e202f52951.bin',
                        'size' => 1494992,
                        'digest' => 'f0eae5ff3654419a264e4d40072eac84'
                    ]
                ],
                [
                    'id' => 49,
                    'module' => 'pics',
                    'version' => 2442001,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/3b01a7e7-54b4-4fb1-981c-1d8a0d6af060.bin',
                        'size' => 131072,
                        'digest' => '238b72bd540037f4ee33bf5307684713'
                    ]
                ],
                [
                    'id' => 48,
                    'module' => 'lans',
                    'version' => 2444003,
                    'file' => [
                        'url' => 'http://api.eu-pet.com/firmware/T4/1.625/e19cef1a-f5a5-4ed2-8d44-4c2c78d11571.bin',
                        'size' => 712704,
                        'digest' => '36fb8f4ea82f5252d52ec73c9a10b319'
                    ]
                ]
            ]
        ];
    }

    public function toDevSignup(): array {

        return [
            'id' => $this->device->petkit_id,
            'mac' =>  $this->device->mac,
            'sn' =>  $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' =>  $this->device->timezone,
            'locale' =>  $this->device->locale,
            'shareOpen' =>  $this->device->configuration['settings']['shareOpen'],
            'petInTipLimit' =>  $this->device->configuration['settings']['petInTipLimit']
        ];
    }
    public function toDeviceInfo(): array {
        $config = $this->device->configuration['settings'];
        $k3 = $this->device->configuration['k3Device'] ?? false;

        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret,
            'timezone' => $this->device->timezone,
            'locale' => $this->device->locale,
            'shareOpen' => (int)$config['shareOpen'],
            'typeCode' => (int)$config['typeCode'],
            'withK3' => (int)isset($k3['id']),
            'k3Id' => (int)($k3['id'] ?? 0),
            'btMac' => $this->device->bt_mac,
            'settings' => [
                'sandType' => (int)$config['sandType'],
                'manualLock' => (int)$config['manualLock'],
                'lightMode' => (int)$config['lightMode'],
                'clickOkEnable' => (int)$config['clickOkEnable'],
                'lightRange' =>$config['lightRange'],
                'autoWork' => (int)$config['autoWork'],
                'fixedTimeClear' =>$config['fixedTimeClear'],
                'downpos' => (int)$config['downpos'],
                'deepRefresh' => (int)$config['deepRefresh'],
                'autoIntervalMin' =>$config['autoIntervalMin'],
                'stillTime' =>$config['stillTime'],
                'unit' => (int)$config['unit'],
                'language' =>$config['language'],
                'avoidRepeat' => (int)$config['avoidRepeat'],
                'underweight' => (int)$config['underweight'],
                'kitten' => (int)$config['kitten'],
                'stopTime' =>$config['stopTime'],
                'sandFullWeight' => $config['sandFullWeight'],
                'disturbMode' => (int)$config['disturbMode'],
                'disturbRange' =>$config['disturbRange'],
                'sandSetUseConfig' =>$config['sandSetUseConfig'],
                'k3Config' => $config['k3Config'],
                'relateK3Switch' => (int)$config['relateK3Switch'] ?? 0,
                'lightest' =>$config['lightest'],
                'deepClean' => (int)$config['deepClean'],
                'removeSand' => (int)$config['removeSand'],
                'bury' => (int)$config['bury'],
            ],
            'k3Device' => [
                'id' => (int)($k3['id'] ?? 0),
                'mac' => $k3['mac'] ?? '',
                'sn' => $k3['sn'] ?? '',
                'secret' => $k3['secret'] ?? '',
            ],
            'multiConfig' => (bool)($k3['id'] ?? 0) > 0,
            'petInTipLimit' => (int)$config['petInTipLimit'],
        ];
    }

    public function toDeviceMultiConfig(): array {
        $setting = $this->getDevice()->configuration['settings'];

        return [
            'lightMultiRange' => $setting['lightRange'] ?? [],
            'distrubMultiRange' => $setting['distrubRange'] ?? [],
        ];
    }
}
