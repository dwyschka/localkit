<?php

namespace App\Console\Commands;

use Exception;
use App\Http\Resources\MQTT\SuccessResource;
use App\Models\Device;
use App\MQTT\GenericReply;
use App\MQTT\Localkit;
use App\MQTT\OtaMessage;
use App\MQTT\UserGet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use PhpMqtt\Client\MqttClient;

class LocalkitListen extends Command
{
    public static $topics = [
        '/#'
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'localkit:listen';

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
        $mqtt = MQTT::connection('localkit');
        pcntl_signal(SIGINT, function () use ($mqtt) {
            $mqtt->interrupt();
        });

        $definitions = Device::all()->map(fn(Device $device) => $device->definition());

        // Every device name (d_<type>_<serial>) we know about, and every topic we
        // already handle for them. Anything that carries a device name but isn't in
        // the known set is a topic we don't have on our radar yet - and gets logged.
        $deviceNames = $definitions
            ->map(fn($definition) => $definition->getDevice()->deviceName())
            ->all();

        $knownTopics = $definitions
            ->flatMap(fn($definition) => array_merge(
                array_keys($definition->stateTopics()),
                $definition->subscribedTopics(),
            ))
            ->unique()
            ->all();

        $output = $this->output;
        $output->writeln('Listening for messages...');
        $mqtt->subscribe('#', function(string $topic, $message) use($mqtt, $definitions, $deviceNames, $knownTopics, $output) {

            $output->writeln(sprintf('Got Message on Topic %s, %s', $topic, json_encode($message)));

            $this->logUnknownDeviceTopic($topic, $message, $deviceNames, $knownTopics, $output);

            $definitions->each(function ($definition) use ($topic, $message) {
                $device = $definition->getDevice();
                if ($device->debug_mode && str_contains($topic, $device->deviceName())) {
                    $this->logDeviceMqttMessage($device, $topic, $message);
                }
            });

            $message = json_decode($message, false);

            if(Localkit::isLocalkitTopic($topic)) {
                return Localkit::route($topic, $message);
            }

            $this->updateLastTimestamp($topic, $message, $definitions);

            try {
                $definitions->each(function ($definition) use ($topic, $message, $output) {
                    collect($definition->stateTopics())
                        ->each(function ($callback, $stateTopic) use ($topic, $message, $definition, $output) {
                            if ($stateTopic === $topic) {
                                $output->writeln(sprintf('Found State Topic %s', $stateTopic));

                                // $definitions was built once at process startup and this
                                // listener runs forever, so the wrapped Device model is a
                                // long-lived in-memory copy - without refreshing it here,
                                // any change made elsewhere (e.g. a schedule/settings edit
                                // in the Filament admin, a separate PHP-FPM request) is
                                // invisible to it, and the next state message rebuilds
                                // 'configuration' from the stale snapshot and silently
                                // overwrites that change back out.
                                $definition->getDevice()->refresh();
                                $callback($definition->getDevice(), $topic, $message);
                            }
                        });
                });
            } catch (Exception $exception) {
                $output->writeln(sprintf('Error: %s', $exception->getMessage()));
                Log::error($exception->getMessage());
            }
        }, MqttClient::QOS_AT_MOST_ONCE);

        $mqtt->loop(true, false);
    }

    /**
     * Log any topic that references one of our devices but that we don't
     * already handle, so unexpected topics don't slip by unnoticed.
     */
    private function logUnknownDeviceTopic(string $topic, $message, array $deviceNames, array $knownTopics, $output): void
    {
        $referencesDevice = collect($deviceNames)
            ->contains(fn(string $deviceName) => str_contains($topic, $deviceName));

        if (!$referencesDevice) {
            return;
        }

        if (in_array($topic, $knownTopics, true)) {
            return;
        }

        $output->writeln(sprintf('<comment>Unknown device topic %s</comment>', $topic));

        $channel = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/localkit_unknown_topics.log'),
            'level' => 'debug',
        ]);

        Log::stack([$channel])->debug('[MQTT] ' . $topic, [
            'topic' => $topic,
            'message' => $message,
        ]);
    }

    /**
     * Every MQTT event message carries its own top-level params.timestamp
     * (confirmed via live captures, e.g. mqtt_discern.txt) - update it
     * regardless of whether the topic is one we otherwise handle. Uses a
     * direct query builder update rather than $device->update() so this
     * doesn't fire Device::booted()'s 'updated' hook (Home Assistant
     * republish) on every single message a device sends.
     */
    private function updateLastTimestamp(string $topic, $message, $definitions): void
    {
        $timestamp = $message?->params?->timestamp ?? null;

        if ($timestamp === null) {
            return;
        }

        $definitions->each(function ($definition) use ($topic, $timestamp) {
            $device = $definition->getDevice();
            if (str_contains($topic, $device->deviceName())) {
                Device::whereKey($device->id)->update(['last_timestamp' => $timestamp]);
            }
        });
    }

    private function logDeviceMqttMessage(Device $device, string $topic, string $message): void
    {
        $channel = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/device_' . $device->serial_number . '.log'),
            'level' => 'debug',
        ]);

        Log::stack([$channel, 'single'])->debug('[MQTT] ' . $topic, [
            'topic' => $topic,
            'message' => $message,
        ]);
    }

}
