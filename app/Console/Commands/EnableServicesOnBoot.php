<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        $this->startProcesses();
    }

    private function startProcesses()
    {
        $names = ['localkit-homeassistant'];

        foreach($names as $name) {
            if(!config(sprintf('app.enable.%s', $name))) {
                continue;
            }
            $this->info(sprintf('Starting %s', $name));;
            $this->call('supervisor', ['action' => 'start', 'serviceName' => $name]);
        }
    }

}
