<?php

namespace App\Petkit\Devices;

use App\DTOs\PetkitDTOInterface;
use App\Helpers\JsonHelper;
use App\Homeassistant\HomeassistantTopic;
use App\Homeassistant\Interfaces\Snapshot;
use App\Jobs\ServiceConnect;
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
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

/**
 * Device definition for the PetKit W7H "Eversweet Ultra" smart fountain
 * (camera + auto water change + heater). See IMPLEMENT/w7h_config_keys.csv and
 * IMPLEMENT/w7h_actions.csv for the firmware-level reverse-engineering this
 * class is based on.
 *
 * Only `snapshot` (via Go2RTC, no vendor MQTT contract needed) and the
 * generic settings/property_set push (shared infra, already proven by the
 * other NextGen devices) are wired to real MQTT traffic. The remaining
 * commands from w7h_actions.csv (power/start/stop/lapse/play_sound/...) are
 * known to exist on the device but their exact outbound topic/payload was
 * not confirmed during firmware analysis, so they are intentionally left
 * unimplemented rather than guessed.
 */
class PetkitW7H implements DeviceDefinition, Snapshot, BluetoothProxyInterface, HasCamera
{
    public static $workingStates = [
        DeviceStates::WORKING, DeviceStates::IDLE,
    ];
    protected array $actions = [
        DeviceActions::TAKE_SNAPSHOT,
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
        ];
    }

    public static function deviceName()
    {
        return 'Petkit Eversweet Ultra';
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
            sprintf('/sys/%s/%s/thing/event/property_post/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message);
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
            sprintf('/sys/%s/%s/thing/event/drink_detect/post', $this->device->productKey(), $this->device->deviceName()) => function (Device $device, string $topic, \stdClass|null $message) {
                $this->parseState($device, $message, mutate: function (\stdClass $state) use ($device) {
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
            'error' => null,
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

        return $hasAction;
    }

    public function takeSnapshot(Device $record): void
    {
        TakeSnapshot::dispatchSync($record);
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration\PetkitW7H::fromDevice($this->getDevice());
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
    public function settings(\stdClass $message)
    {
        $configuration = $this->configurationDefinition();
        $keys = get_object_vars($message);

        foreach ($keys as $attributeName => $value) {
            $configuration->$attributeName = $value;
        }
        $this->getDevice()->update(['configuration' => $configuration]);
    }

    #[HomeassistantTopic('action/start')]
    public function action(\stdClass $message): void
    {
        $action = $message->action;
        switch ($action) {
            case 'snapshot':
                $this->takeSnapshot($this->getDevice());
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

    private function updateConfiguration(mixed $content): array
    {
        $settings = $this->getDevice()->configuration();

        //IP - reported inside the `other` string, key/value may or may not be quoted (e.g. Ip:x.x.x.x, Ip:"x.x.x.x" or "Ip":x.x.x.x)
        $pattern = '/"?Ip"?:\\\\?"?(\d{1,3}(?:\.\d{1,3}){3})/';
        $match = Str::of($content->other ?? '')->match($pattern);

        if ($match->value() !== null) {
            $settings->ipAddress = $match->value();
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
