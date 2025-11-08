<?php

namespace App\Console\Commands;

use App\Clients\SupervisorClient;
use App\Management\Supervisor;
use Illuminate\Console\Command;

class SupervisorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supervisor {action} {serviceName}';

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
        'homeassistant', 'go2rtc', 'localkit'
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

        $supervisor = app(Supervisor::class);

        try {
            $supervisor->running($serviceName);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }


        switch($action) {
            case 'start':
                $supervisor->start($serviceName);
                break;
            case 'stop':
                $supervisor->stop($serviceName);
                break;
            case 'restart':
                $supervisor->restart($serviceName);
                break;
        }

        try {
            $supervisor->running($serviceName);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->info(sprintf('Service %s is %s', $serviceName, $supervisor->running($serviceName) ? 'running' : 'not running'));

    }
}
