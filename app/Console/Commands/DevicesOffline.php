<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Petkit\DeviceStates;
use Illuminate\Console\Command;

class DevicesOffline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:devices-offline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Device::all()->each(function(Device $device) {
            $device->update([
                'mqtt_connected' => false,
                'working_state' => DeviceStates::IDLE->value
            ]);
        });
    }
}
