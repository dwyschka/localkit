<?php

namespace App\Petkit\Devices;

use App\Helpers\JsonHelper;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Jobs\SetProperty;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use App\Petkit\DeviceActions;
use App\Petkit\DeviceDefinition;
use App\Petkit\DeviceStates;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class PetkitEversweetSolo2 implements DeviceDefinition
{
    public const ID = 14;

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
    public function subscribedTopics(): array {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/end', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/start', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    public function stateTopics(): array {
        return [
            sprintf('/sys/%s/%s/thing/event/work_continue/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::CLEANING
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/work_suspend/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::IDLE
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/work_start/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::CLEANING
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/clean_over/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::IDLE
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/dump_over/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::IDLE
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/reset_over/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::IDLE
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/pet_in/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::PET_IN
                ]);
                $this->reply($topic, $message);

            },
            sprintf('/sys/%s/%s/thing/event/pet_out/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $device->update([
                    'working_state' => DeviceStates::IDLE
                ]);
                $this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_start/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){

                $msg = $message->params->content;
                $msg = json_decode($msg, false);

                $device->update([
                    'working_state' => DeviceStates::ERROR,
                    'error' => $this->parseErrorMessage($msg->err)
                ]);
                Log::info('got message', ['message' => $msg]);
                //$this->reply($topic, $message);
            },
            sprintf('/sys/%s/%s/thing/event/error_over/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){

                Log::info('got message', ['message' => $message]);


                $device->update([
                    'working_state' => DeviceStates::IDLE,
                    'error' => null
                ]);
                $this->reply($topic, $message);
            },
            sprintf('/ota/device/inform/%s/%s', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message){
                $message = OtaMessage::send($device);
                MQTT::publish($message->getTopic(), $message->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/data_get/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);

                $msg = UserGet::reply($device->productKey(), $device->deviceName(), $message);
                MQTT::publish($msg->getTopic(), $msg->getMessage());
            },
            sprintf('/sys/%s/%s/thing/event/property/post', $this->device->productKey(), $this->device->deviceName()) => function(Device $device, string $topic, \stdClass|null $message) {
                $this->reply($topic, $message);

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
        switch($action) {
            case DeviceActions::START_ODOUR:
            case DeviceActions::START_LIGHTNING:
                return $hasAction && $hasK3;

            case DeviceActions::START_MAINTENANCE:
                return $hasAction && $this?->device?->working_state == DeviceStates::IDLE;

            case DeviceActions::STOP_MAINTENANCE:
                return $hasAction && $this?->device?->working_state == DeviceStates::MAINTENANCE;
        }

        return $hasAction;
    }

    private function reply(string $topic, ?\stdClass $message)
    {
        $generic = new GenericReply($topic, $message);
        MQTT::publish($generic->getTopic(), $generic->getMessage());
    }

    public function startCleaning(Device $record) {
        ServiceStart::dispatchSync($record,  0);
    }

    public function startMaintenance(Device $record) {
        ServiceStart::dispatchSync($record,  9);
    }

    public function stopMaintenance(Device $record) {
        ServiceEnd::dispatchSync($record,  9);
    }

    public function cleanLitter(Device $record) {
        ServiceStart::dispatchSync($record,  1);
    }
    public function startOdour(Device $record) {
        ServiceStart::dispatchSync($record,  2);
    }

    public function startLightning(Device $record) {
        ServiceStart::dispatchSync($record,  7);
    }

    private function parseErrorMessage($err): string
    {
        Log::info('Got message', [$err]);
        switch($err) {
            case "hallT":
                return "The lid is not closed";
            case "full":
                return "The bin is full";
        }
        return "Unknown error ($err)";
    }

    public static function deviceName() {
        return 'Petkit Pura Max';
    }
    public function defaultConfiguration() {
        return [
            'shareOpen' => 0,
            'withK3' => 0,
            'typeCode' => 1,
            'litter' => [
              'weight' => 0,
              'usedTimes' => 0,
              'percent' => 100,
            ],
            'settings' => [
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
                'bury' => 0
            ],
            'petInTipLimit' => 15
        ];
    }

    public function propertyChange(Device $device):void {
        $difference = JsonHelper::difference($device->getOriginal('configuration')['settings'], $device->configuration['settings']);
        SetProperty::dispatchSync($device, $difference);
    }
}
