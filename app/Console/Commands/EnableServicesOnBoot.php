<?php

namespace App\Console\Commands;

use App\Management\Go2RTC;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class EnableServicesOnBoot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:enable-services-on-boot';

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
        $this->validateGo2RTC();
        $this->startProcesses();
    }

    private function startProcesses()
    {
        $names = ['localkit-homeassistant', 'localkit-go2rtc'];

        foreach($names as $name) {
            if(!config(sprintf('app.enable.%s', $name))) {
                continue;
            }
            $this->info(sprintf('Starting %s', $name));;
            $this->call('supervisor', ['action' => 'start', 'serviceName' => $name]);
        }
    }

    private function validateGo2RTC(): void
    {
        $devices = Device::all()->filter(function(Device $device) {
            return $device->isNextGen();
        });
        $go2rtc = app(Go2RTC::class);

        if($devices->isEmpty()) {
            return;
        }

        $go2rtc->createConfigYml($devices);
        $go2rtc->enable();
    }

}
