<?php

namespace App\Jobs;

use App\Models\Device;
use App\MQTT\FeedRealtimeMessage;
use App\MQTT\PropertySetMessage;
use App\MQTT\ServiceStartMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpMqtt\Client\Facades\MQTT;

class TakeSnapshot implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected Device $device)
    {
        //
    }

    //{"method":"thing.service.feed_realtime","id":"18432013529","params":{"amount":10,"id":"r_20250629_81113389_81099-1"},"version":"1.0.0"}
    /**
     * Execute the job.
     */
    public function handle(): void
    {

        $mp4FileName = sprintf('snapshot_%s_%s.mp4', $this->device->name, Carbon::now()->format('YmdHis'));
        $jpegFileName = Str::of($mp4FileName)->replace('mp4', 'jpg');
        $settings = $this->device->configuration;
        $ip = $settings['state']['ipAddress'];

        if(is_null($ip)) {
            Log::error('No IP set');
            return;
        }


        Storage::disk('local')->writeStream(
            $mp4FileName, Http::get(sprintf('http://%s:1984/api/frame.mp4?src=camera', $ip)
            )->resource());

        $inputPath = Storage::disk('local')->path($mp4FileName);
        $outputPath = Storage::disk('public')->path($jpegFileName);

        $command = 'ffmpeg -i "%s"  -vframes 1 "%s"';

        shell_exec(
            sprintf($command, $inputPath, $outputPath)
        );

        Storage::disk('local')->delete($mp4FileName);

        /** @var \App\Petkit\Devices\Configuration\PetkitYumshareSolo $configuration */
        $configuration = $this->device->configurationDefinition();

        $lastSnapshot = $configuration->getLastSnapshot();
        if (!is_null($lastSnapshot)) {
            Storage::disk('public')->delete(
                basename($lastSnapshot)
            );
        }

        $configuration->setLastSnapshot(
            Storage::disk('public')->url($jpegFileName)
        );

        $this->device->update([
            'configuration' => $configuration->toArray()
        ]);

    }
}
