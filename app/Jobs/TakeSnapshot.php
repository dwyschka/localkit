<?php

namespace App\Jobs;

use App\Petkit\Devices\Configuration\PetkitYumshareSolo;
use App\Management\Go2RTC;
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

        $go2rtc = app(Go2RTC::class);

        // The configured default (go2rtc.stream) is a guess - only trust it if
        // the device's go2rtc actually registered a stream under that name,
        // otherwise fall back to whatever it does have. Avoids "stream not
        // found" when a device names its stream differently.
        $streams = $go2rtc->streams($this->device);

        if (empty($streams)) {
            Log::error('No go2rtc streams available', ['device' => $this->device->getKey()]);
            return;
        }

        $stream = in_array(config('go2rtc.stream'), $streams, true) ? config('go2rtc.stream') : $streams[0];

        Storage::disk('snapshots')->writeStream(
            $jpegFileName, Http::get($go2rtc->frameUrl($this->device, $stream))->resource()
        );



        /** @var PetkitYumshareSolo $configuration */
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
