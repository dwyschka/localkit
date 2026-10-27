<?php

namespace Tests\Feature;

use App\Jobs\ServiceBle;
use App\Models\BluetoothDevice;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * A W5's own status reports are saved like any other change to its
 * configuration, and that save is what sends Power/Mode to the fountain.
 * Only a change made here (form or Home Assistant) may do that - a report
 * must not be echoed back.
 */
class W5ReportEchoTest extends TestCase
{
    use RefreshDatabase;

    /** CMD 230 from an Eversweet Solo 2: power on, mode 2 (Smart). */
    private const SMART_STATUS = 'AQIAAAAAAADgRWEBAAB1bAMDAQIAAAWgAAUoAWgA';

    private BluetoothDevice $fountain;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.enable.homeassistant' => false]);
        Bus::fake([ServiceBle::class]);

        $proxy = Device::create([
            'serial_number' => 'D4PROXY0001',
            'device_type' => 'd4',
            'secret' => 'secret',
            'mqtt_subdomain' => 'localkit',
            'firmware' => '1.0.0',
            'mac' => 'aa:bb:cc:dd:ee:ff',
            'bt_mac' => 'aa:bb:cc:dd:ee:f0',
            'ap_mac' => 'aa:bb:cc:dd:ee:f1',
            'chip_id' => 'chip',
            'timezone' => 1,
            'locale' => 'Europe/London',
        ]);

        $this->fountain = BluetoothDevice::create([
            'name' => 'Fountain',
            'type' => 'w5',
            'mac' => 'a4c1385e7ff8',
            'secret' => '22e11190d5a8',
            'petkit_id' => '400093424',
            'link_with' => $proxy->id,
        ]);
    }

    private function storedMode(): int
    {
        return (int) $this->fountain->fresh()->configuration['states']['mode'];
    }

    public function test_a_report_is_stored_but_not_sent_back(): void
    {
        $this->assertSame(1, $this->storedMode());

        $handled = $this->fountain->device()->handleMessage((object) ['cmd' => 230, 'data' => self::SMART_STATUS]);

        $this->assertTrue($handled);
        $this->assertSame(2, $this->storedMode());
        Bus::assertNotDispatched(ServiceBle::class);
    }

    public function test_a_change_made_here_is_still_sent(): void
    {
        $configuration = $this->fountain->configuration;
        $configuration['states']['mode'] = 2;
        $this->fountain->configuration = $configuration;
        $this->fountain->save();

        Bus::assertDispatched(ServiceBle::class);
    }
}
