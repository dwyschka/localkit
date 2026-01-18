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

        $jpegFileName = sprintf('snapshot_%s_%s.jpeg', $this->device->name, Carbon::now()->format('YmdHis'));
        $settings = $this->device->configuration;
        $ip = $settings['states']['ipAddress'];


        if(is_null($ip)) {
            Log::error('No IP set');
            return;
        }


        Storage::disk('snapshots')->writeStream(
            $jpegFileName, Http::get('http://localhost:1984/api/frame.jpeg?src='. $this->device->name)->resource()
        );



        /** @var \App\Petkit\Devices\Configuration\PetkitYumshareSolo $configuration */
        $configuration = $this->device->configuration();

        $lastSnapshot = $configuration->lastSnapshot;
        if (!is_null($lastSnapshot)) {
            Storage::disk('snapshots')->delete(
                basename($lastSnapshot)
            );
        }

        $configuration->lastSnapshot = $jpegFileName;

        $this->device->update([
            'configuration' => $configuration->toArray()
        ]);

    }
}
