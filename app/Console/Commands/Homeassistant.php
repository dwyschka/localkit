<?php

namespace App\Console\Commands;

use App\Helpers\HomeassistantHelper;
use App\Homeassistant\AutoDiscoveryService;
use App\Homeassistant\HomeassistantTopicService;
use App\Homeassistant\Interfaces\Snapshot;
use App\Homeassistant\Interfaces\Video;
use App\Models\Device;
use Illuminate\Console\Command;
use PhpMqtt\Client\Facades\MQTT;

class Homeassistant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'homeassistant';

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
        $mqtt = MQTT::connection('homeassistant');

        $devices = Device::whereProxyMode(0)->get();

        $devices->each(function(Device $device) use ($mqtt) {
            $definition = $device->definition();
            $mqtt->publish(HomeassistantHelper::deviceTopic($device), $definition->toHomeassistant(), 0, true);

            if($definition instanceof Snapshot) {
                $mqtt->publish(HomeassistantHelper::snapshotTopic($device), $definition->toSnapshot(), 0, true);
            }

            $configuration = $device->definition()->configurationDefinition();
            $service = new AutoDiscoveryService($mqtt);

            $service->discover($configuration);
        });

        die();
        $service = new HomeassistantTopicService($devices);
        $mqtt->subscribe('localkit/#', function($topic, $message) use($service){
            $service->resolve($topic, json_decode($message, false));
        });

        $mqtt->loop(false);
    }
}
