<?php

namespace App\Console\Commands;

use App\Helpers\HomeassistantHelper;
use App\Homeassistant\AutoDiscoveryService;
use App\Homeassistant\HomeassistantTopicService;
use App\Homeassistant\Interfaces\Snapshot;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;

class LocalkitHomeassistant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'localkit:homeassistant';

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

        $devices->each(function (Device $device) use ($mqtt) {
            $definition = $device->definition();
            $mqtt->publish(HomeassistantHelper::deviceTopic($device), $definition->toHomeassistant(), 0, true);

            if ($definition instanceof Snapshot) {
                try {
                    $snapshotMessage = $definition->toSnapshot();
                    if (!is_null($snapshotMessage)) {
                        $mqtt->publish(HomeassistantHelper::snapshotTopic($device), $snapshotMessage, 0, true);
                    }
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            }

            $configuration = $device->definition()->configurationDefinition();
            $service = new AutoDiscoveryService($mqtt);

            $service->discover($configuration);
        });

        $service = new HomeassistantTopicService($devices);
        $mqtt->subscribe('localkit/#', function ($topic, $message) use ($service) {
            $service->resolve($topic, json_decode($message, false));
        });

        $mqtt->loop(true);
    }
}
