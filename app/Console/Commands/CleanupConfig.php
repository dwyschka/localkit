<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class CleanupConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-config';

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
        $devices = Device::all();

        foreach($devices as $device) {

            $configuration = $device->configuration;

            foreach($configuration as $configKey => $configValues) {

                foreach($configValues as $key => $value) {
                    if(is_null($value)) {
                        unset($configuration[$configKey][$key]);
                    }
                }
            }

            $device->update(['configuration' => $configuration]);
        }
    }
}
