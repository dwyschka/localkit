<?php

namespace App\Petkit\Devices;

use App\Helpers\JsonHelper;
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
use PhpMqtt\Client\Facades\MQTT;

class PetkitPuraMax implements DeviceDefinition
{
    public const ID = 10;

    protected array $actions = [
        DeviceActions::START_CLEAN,
        DeviceActions::START_MAINTENANCE,
        DeviceActions::STOP_MAINTENANCE,
        DeviceActions::CLEAN_LITTER,
        DeviceActions::START_ODOUR,
        DeviceActions::START_LIGHTNING
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
                    'error' => __(sprintf('petkit.error.%s' . $msg->err))
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
                MQTT::publish($message->getTopic(), $message->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/data_get/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);
                $msg = UserGet::reply($device->productKey(), $device->deviceName(), $message);
                MQTT::publish($msg->getTopic(), $msg->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);

                if (!empty($message?->params?->litter)) {
                    $configuration = $device->configuration;
                    $configuration['litter'] = (array)$message->params->litter;
                    $device->update(['configuration' => $configuration]);
                }

                if (!isset($message?->params?->work_state)) {
                    $device->update(['working_state' => DeviceStates::IDLE->value]);
                } else {
                    $deviceStatus = $this->deviceStatus($message->params->work_state->work_mode);
                    $device->update(['working_state' => $deviceStatus]);
                }

                $msg = UserGet::replyToState($device->productKey(), $device->deviceName(), $message);
                MQTT::publish($msg->getTopic(), $msg->getMessage());
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
        $hasK3 = $this->device->configuration['withK3'] ?? false;
        if ($this->device->proxy_mode == 1) {
            return false;
        }
        switch ($action) {
            case DeviceActions::CLEAN_LITTER:
            case DeviceActions::START_MAINTENANCE:
            case DeviceActions::START_CLEAN:
                return $hasAction && $this->device->working_state === DeviceStates::IDLE->value;

            case DeviceActions::START_ODOUR:
            case DeviceActions::START_LIGHTNING:
                return $hasAction && $hasK3;

            case DeviceActions::STOP_MAINTENANCE:
                return $hasAction && $this?->device?->working_state == DeviceStates::MAINTENANCE->value;
        }

        return $hasAction;
    }

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = GenericReply::reply($topic, $message);
        MQTT::publish($generic->getTopic(), $generic->getMessage());
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

    public static function deviceName()
    {
        return 'Petkit Pura Max';
    }

    public function defaultConfiguration()
    {
        return [
            'litter' => [
                'weight' => 0,
                'usedTimes' => 0,
                'percent' => 100,
            ],
            'settings' => [
                'shareOpen' => 0,
                'withK3' => 0,
                'typeCode' => 1,
                'sandType' => 1,
                'manualLock' => 0,
                'clickOkEnable' => 1,
                'lightMode' => 1,
                'lightRange' => [0, 1440],
                'autoWork' => 1,
                'fixedTimeClear' => 0,
                'downpos' => 0,
                'deepRefresh' => 0,
                'autoIntervalMin' => 0,
                'stillTime' => 30,
                'unit' => 0,
                'language' => 'de_DE',
                'avoidRepeat' => 1,
                'underweight' => 0,
                'kitten' => 0,
                'stopTime' => 600,
                'sandFullWeight' => [3200, 5800, 3000, 3200, 3200],
                'disturbMode' => 0,
                'disturbRange' => [40, 520],
                'sandSetUseConfig' => [
                    [40, 60, 85],
                    [40, 60, 85],
                    [40, 60, 85],
                    [40, 60, 85]
                ],
                'k3Config' => [
                    'config' => [
                        'standard' => [5, 30],
                        'lightness' => 100,
                        'lowVoltage' => 5,
                        'refreshTotalTime' => 11500,
                        'singleRefreshTime' => 25,
                        'singleLightTime' => 120
                    ]
                ],
                'relateK3Switch' => 1,
                'lightest' => 1840,
                'deepClean' => 0,
                'removeSand' => 1,
                'bury' => 0,
                'petInTipLimit' => 15
            ],
        ];
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
        return new Configuration\PetkitPuraMax($this->getDevice());
    }
}
