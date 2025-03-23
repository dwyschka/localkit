<?php

namespace App\Console\Commands;

use App\Http\Resources\MQTT\SuccessResource;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class MqttListen extends Command
{
    public static $topics = [
        '/#'
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen';

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
        $mqtt = MQTT::connection();
        pcntl_signal(SIGINT, function () use ($mqtt) {
            $mqtt->interrupt();
        });

        $definitions = Device::whereProxyMode(0)->get()->map(fn(Device $device) => $device->definition());

        $output = $this->output;
        $mqtt->subscribe('#', function(string $topic, $message) use($mqtt, $definitions, $output) {

            $output->writeln(sprintf('Got Message on Topic %s', $topic));
            $message = json_decode($message, false);
            try {
                $definitions->each(function ($definition) use ($topic, $message, $output) {
                    collect($definition->stateTopics())
                        ->each(function ($callback, $stateTopic) use ($topic, $message, $definition, $output) {
                            if ($stateTopic === $topic) {
                                $output->writeln(sprintf('Found State Topic %s', $stateTopic));
                                $callback($definition->getDevice(), $topic, $message);
                            }
                        });
                });
            } catch (\Exception $exception) {
                Log::error($exception->getMessage());
            }
        }, MqttClient::QOS_AT_LEAST_ONCE);

        $mqtt->loop(false, false);
    }

}
