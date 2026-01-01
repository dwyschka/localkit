<?php

namespace App\Console\Commands;

use App\Management\S6;
use Illuminate\Console\Command;

class ServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service {action} {serviceName}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected array $actions = [
        'start', 'stop', 'restart'
    ];

    protected array $services = [
        'localkit-homeassistant', 'localkit-go2rtc', 'localkit-listen'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        if(!in_array($action, $this->actions) || !in_array($this->argument('serviceName'), $this->services)) {
            $this->error('Invalid action/service');
            return;
        }

        $serviceName = $this->argument('serviceName');

        $s6 = app(S6::class);

        try {
            $s6->running($serviceName);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }


        switch($action) {
            case 'start':
                $s6->start($serviceName);
                break;
            case 'stop':
                $s6->stop($serviceName);
                break;
            case 'restart':
                $s6->restart($serviceName);
                break;
        }

        try {
            $s6->running($serviceName);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->info(sprintf('Service %s is %s', $serviceName, $s6->running($serviceName) ? 'running' : 'not running'));

    }
}
