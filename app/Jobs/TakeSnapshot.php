<?php

namespace App\Jobs;

use App\Management\Rtsp;
use App\Models\Device;
use App\Petkit\Devices\Configuration\ConfigurationInterface;
use App\Petkit\Interfaces\HasCamera;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Grabs a still frame from the device's camera and stores it on the snapshots
 * disk, so it can be published to Home Assistant as the device's Snapshot
 * image entity (see the #[Image] attribute on each camera device's
 * Configuration) and shown in the panel's Media section.
 *
 * The frame comes straight off the device's RTSP stream via ffmpeg - the
 * cameras expose nothing else.
 */
class TakeSnapshot implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Device $device)
    {
        //
    }

    public function handle(): void
    {
        $rtsp = app(Rtsp::class);
        $input = $rtsp->ffmpegInput($this->device);

        if ($input === null) {
            Log::error('Cannot take snapshot, no IP set', ['device' => $this->device->getKey()]);

            return;
        }

        $jpeg = $this->capture($input);

        if ($jpeg === null) {
            return;
        }

        $fileName = sprintf('snapshot_%s_%s.jpeg', $this->device->name, Carbon::now()->format('YmdHis'));

        Storage::disk('snapshots')->put($fileName, $jpeg);

        /** @var HasCamera&ConfigurationInterface $configuration */
        $configuration = $this->device->configuration();

        $lastSnapshot = $configuration->lastSnapshot;
        if (!is_null($lastSnapshot)) {
            Storage::disk('snapshots')->delete(
                basename($lastSnapshot)
            );
        }

        $configuration->lastSnapshot = $fileName;

        $this->device->update([
            'configuration' => $configuration->toArray()
        ]);
    }

    /**
     * @param  array<int, string>  $input
     */
    private function capture(array $input): ?string
    {
        $timeout = (float) config('camera.thumbnail.timeout', 10);

        $process = new Process([
            (string) config('camera.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            '-y',
            ...$input,
            '-an',
            '-frames:v', '1',
            '-q:v', '3',
            '-f', 'image2pipe',
            '-vcodec', 'mjpeg',
            'pipe:1',
        ]);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (Throwable $e) {
            Log::error('Snapshot ffmpeg error', [
                'device' => $this->device->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::error('Snapshot ffmpeg failed', [
                'device' => $this->device->getKey(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return null;
        }

        $output = $process->getOutput();

        if ($output === '') {
            Log::error('Snapshot ffmpeg produced no frame', ['device' => $this->device->getKey()]);

            return null;
        }

        return $output;
    }
}
