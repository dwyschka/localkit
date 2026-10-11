<?php

namespace App\Petkit\Devices\Unknown;

use App\Models\Device as DeviceModel;
use App\Petkit\DeviceDefinition;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use Illuminate\Support\Facades\Log;

/**
 * Fallback definition for a device type Localkit has no implementation for.
 *
 * Provisioning hands a device its WiFi credentials and this server's address
 * over Bluetooth without knowing what it is - the device type only shows up
 * afterwards, in the URL of its own sign-up (`/{deviceType}/dev_signup`) and
 * in its X-Device header. A type we don't know used to end that story with an
 * UnhandledMatchError out of Device::definition(), which left the device with
 * no id, no secret and nowhere to go.
 *
 * So an unknown device gets served instead: sign-up, serverinfo, device info
 * and the heartbeat all answer with the type-independent parts of the
 * protocol, which is everything those endpoints actually need. What it does
 * not get is control: no actions, no settings pushed to it, no state parsing -
 * hasAction() says no to everything, and stateTopics() is empty, so every
 * MQTT message it publishes lands in storage/logs/localkit_unknown_topics.log
 * (see LocalkitListen) instead of being interpreted. That log is the starting
 * point for adding a real implementation for the type.
 *
 * Anything else a resource asks of a definition - toDeviceMultiConfig(),
 * toFeedGet(), toK3DeviceInfo(), ... - is answered with an empty array by
 * __call() rather than a 500: for an endpoint we have no payload for, "no
 * data" is the most honest answer, and a device that gets an error back at
 * the wrong moment goes quiet for good. toFeed() is deliberately *not*
 * answered in a way method_exists() can see, since that is what the panel and
 * SetProperty use to decide whether a device has a feeding schedule.
 */
class Device implements DeviceDefinition
{
    /** Nothing is controllable on a device we don't know. */
    protected array $actions = [];

    public function __construct(protected DeviceModel $device)
    {
    }

    /**
     * Not static, unlike every implemented device's deviceName(), because the
     * name carries the type code the device reported - which is the only thing
     * we know about it. Every caller (Homeassistant\BaseEntity, the device
     * listing) goes through an instance, so this stays compatible.
     */
    public function deviceName(): string
    {
        return sprintf('Unknown Device (%s)', $this->device->device_type ?: 'no type');
    }

    public function getDevice(): DeviceModel
    {
        return $this->device;
    }

    public function hasAction(string $action): bool
    {
        return false;
    }

    /**
     * Nothing is parsed: without knowing the firmware's state shape, any
     * mapping would be a guess. Leaving this empty is also what makes
     * LocalkitListen log the device's whole MQTT traffic as unknown topics.
     */
    public function stateTopics(): array
    {
        return [];
    }

    /**
     * The two topics every PetKit firmware we have seen subscribes to,
     * independent of type - handed to the broker so the device's connection
     * looks like any other, even though Localkit never publishes to them for
     * an unknown device.
     */
    public function subscribedTopics(): array
    {
        return [
            sprintf('/ota/device/upgrade/%s/%s', $this->device->productKey(), $this->device->deviceName()),
            sprintf('/sys/%s/%s/thing/service/property/set', $this->device->productKey(), $this->device->deviceName()),
        ];
    }

    /**
     * No settings are known, so nothing is ever pushed. A configuration
     * change on an unknown device is a panel-side note (e.g. its IP address),
     * not a command.
     */
    public function propertyChange(DeviceModel $device): void
    {
        Log::debug('Skipping property push for unknown device type', [
            'device_id' => $device->id,
            'device_type' => $device->device_type,
        ]);
    }

    public function configurationDefinition(): ConfigurationInterface
    {
        return Configuration::fromDevice($this->getDevice());
    }

    public function configuration(): array
    {
        return $this->configurationDefinition()->toArray();
    }

    public function resetConfiguration(): array
    {
        return (new Configuration([]))->toArray();
    }

    public function toHomeassistant()
    {
        return json_encode($this->configurationDefinition()->toArray());
    }

    public function toDevSignup(): array
    {
        return $this->toDeviceInfo();
    }

    public function toDeviceInfo(): array
    {
        return [
            'id' => $this->device->petkit_id,
            'mac' => $this->device->mac,
            'btMac' => $this->device->bt_mac,
            'sn' => $this->device->serial_number,
            'secret' => $this->device->secret ?? '',
            'timezone' => $this->device->timezone,
            'locale' => $this->device->locale,
            'signupAt' => ($this->device->created_at ?? now())->format('Y-m-d\TH:i:s.v\Z'),
            'settings' => [],
            'userId' => '1',
        ];
    }

    public function toOTA(): array
    {
        return [];
    }

    /**
     * Every other `to…()` a resource might ask for. Returning an empty array
     * keeps the endpoint answering with a well-formed (if empty) response
     * instead of throwing; anything that is not a payload getter is a bug on
     * our side and throws as it would have anyway.
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'to')) {
            Log::info('Unknown device type asked for an unimplemented payload', [
                'device_id' => $this->device->id,
                'device_type' => $this->device->device_type,
                'method' => $name,
            ]);

            return [];
        }

        throw new \BadMethodCallException(sprintf('%s::%s() does not exist', static::class, $name));
    }
}
