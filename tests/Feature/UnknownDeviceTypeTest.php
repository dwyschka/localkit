<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Petkit\Devices\Unknown\Device as UnknownDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Provisioning cannot pick which device signs up - a device is matched on the
 * type it reports, so Localkit has to survive one it has no implementation
 * for. Registration and the heartbeat are what keep such a device on this
 * server at all; the rest of the protocol may be empty, but it must not throw.
 */
class UnknownDeviceTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Saving a device publishes its state to the Home Assistant broker,
        // which is not part of what is under test here.
        config(['app.enable.homeassistant' => false]);
    }

    /** The columns the devices table insists on, none of them type specific. */
    private function unknownDevice(string $serialNumber): Device
    {
        return Device::create([
            'serial_number' => $serialNumber,
            'device_type' => 't9',
            'secret' => 'secret',
            'mqtt_subdomain' => 'localkit',
            'firmware' => '1.0.0',
            'mac' => 'aa:bb:cc:dd:ee:ff',
            'bt_mac' => 'aa:bb:cc:dd:ee:f0',
            'ap_mac' => 'aa:bb:cc:dd:ee:f1',
            'chip_id' => 'chip',
            'timezone' => 2,
            'locale' => 'Europe/Berlin',
        ]);
    }

    public function test_a_device_of_an_unknown_type_can_sign_up(): void
    {
        // The same fields a device reports on sign-up, none of them type
        // specific (the devices table requires all of them).
        $response = $this->post('/6/t9/dev_signup', [
            'sn' => 'UNKNOWN0001',
            'mac' => 'aa:bb:cc:dd:ee:ff',
            'bt_mac' => 'aa:bb:cc:dd:ee:f0',
            'ap_mac' => 'aa:bb:cc:dd:ee:f1',
            'chipid' => 'chip',
            'firmware' => '1.0.0',
            'locale' => 'Europe/Berlin',
        ]);

        $response->assertOk();

        $device = Device::whereSerialNumber('UNKNOWN0001')->firstOrFail();

        $this->assertFalse($device->isSupported());
        $this->assertInstanceOf(UnknownDevice::class, $device->definition());
        $this->assertSame('t9', $device->device_type);

        // Without an id and a secret the device has no identity and nowhere to
        // go - this is the part of sign-up that matters.
        $this->assertNotEmpty($device->petkit_id);
        $this->assertNotEmpty($device->secret);
        $this->assertSame((int) $device->petkit_id, $response->json('result.id'));
        $this->assertSame($device->secret, $response->json('result.secret'));
    }

    public function test_an_unknown_device_gets_a_heartbeat(): void
    {
        $device = $this->unknownDevice('UNKNOWN0002');

        $response = $this->post('/6/poll/localkit/heartbeat', [], [
            'X-Device' => sprintf('id=%s&type=T9', $device->petkit_id),
        ]);

        $response->assertOk();
        $this->assertNotNull($device->fresh()->last_heartbeat);
    }

    public function test_an_unknown_device_offers_no_actions_and_no_payloads(): void
    {
        $device = $this->unknownDevice('UNKNOWN0003');

        $definition = $device->definition();

        $this->assertFalse($definition->hasAction('start_clean'));
        $this->assertSame([], $definition->stateTopics());
        $this->assertSame([], $definition->toDeviceMultiConfig());
        // The discriminator the panel and SetProperty use for "has a feeding
        // schedule" - an unknown device must not look like a feeder.
        $this->assertFalse(method_exists($definition, 'toFeed'));
        $this->assertStringContainsString('Unknown Device', $definition->deviceName());
    }
}
