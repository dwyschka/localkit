<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password')]
        );

        $localIp = trim((string) config('petkit.local_ip'));
        $deviceIp = (!empty($localIp) && filter_var($localIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            ? $localIp
            : '192.168.1.150';

        \App\Models\Device::withoutEvents(function () use ($deviceIp) {
            \App\Models\Device::updateOrCreate(
                ['serial_number' => 'PK-D4SH-0010001'],
                [
                    'name' => 'Fake PetKit YumShare Dual',
                    'device_type' => 'd4sh',
                    'firmware' => '895',
                    'mac' => 'AA:BB:CC:DD:EE:01',
                    'bt_mac' => 'AA:BB:CC:DD:EE:02',
                    'ap_mac' => 'AA:BB:CC:DD:EE:03',
                    'chip_id' => 'fake-chip-10001',
                    'petkit_id' => 10000001,
                    'timezone' => 'America/New_York',
                    'locale' => 'en_US',
                    'mqtt_connected' => 1,
                    'last_heartbeat' => time(),
                    'configuration' => [
                        'settings' => [
                            'amount1' => 5,
                            'amount2' => 5,
                            'lightMode' => 1,
                            'soundEnable' => 1,
                        ],
                        'states' => [
                            'ipAddress' => $deviceIp,
                            'wifiSSID' => 'LocalKit-WiFi',
                            'state' => 'online',
                        ],
                    ],
                ]
            );
        });
    }
}
