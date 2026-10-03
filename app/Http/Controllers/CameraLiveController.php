<?php

namespace App\Http\Controllers;

use App\Management\Rtsp;
use App\Models\Device;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

/**
 * Streams a device's camera live into the browser.
 *
 * No browser can play RTSP, and these devices offer nothing else - no WebRTC,
 * no HLS, no HTTP API. So ffmpeg opens the RTSP session and remuxes it into
 * MPEG-TS, which is written to the response as it arrives; mpegts.js feeds
 * those bytes to Media Source Extensions on the other end (see
 * public/js/localkit/camera-stream.js).
 *
 * Video is copied, never transcoded - the frames go out as the device encoded
 * them, which is what makes this affordable on the machines localkit runs on.
 * Audio is the exception: the devices send G.711/PCM, which MSE cannot decode,
 * so it is re-encoded to AAC (cheap) or dropped via config.
 *
 * The response never ends on its own. It stops when the browser disconnects,
 * which kills the ffmpeg process and with it the RTSP session - important,
 * because the devices only tolerate a handful of concurrent sessions.
 */
class CameraLiveController extends Controller
{
    public function __construct(private readonly Rtsp $rtsp)
    {
    }

    public function __invoke(Device $device): StreamedResponse|Response
    {
        $input = $this->rtsp->ffmpegInput($device);

        if ($input === null) {
            return response('', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $process = new Process([
            (string) config('camera.ffmpeg', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error',
            ...$input,
            ...$this->audioArguments(),
            '-c:v', 'copy',
            // Repeats SPS/PPS ahead of every keyframe. A viewer joining an
            // already running stream otherwise gets no decoder configuration
            // until the camera happens to send one in-band, and MSE cannot
            // initialise without it - the live view just stays black.
            '-bsf:v', 'dump_extra',
            '-f', 'mpegts',
            '-muxdelay', '0',
            '-muxpreload', '0',
            'pipe:1',
        ]);

        // Zero disables Symfony's own timeout; the duration ceiling below is
        // enforced against wall clock instead, so a stalled camera cannot hold
        // the connection open forever either.
        $process->setTimeout(null);

        $maxDuration = (int) config('camera.live.max_duration', 3600);

        return response()->stream(
            function () use ($process, $device, $maxDuration): void {
                // Without this, PHP keeps running the generator after the
                // browser is gone and ffmpeg (plus its RTSP session) survives
                // until the request limit kills the worker.
                ignore_user_abort(false);

                // An MPEG-TS stream has to begin on a 0x47 sync byte. ffmpeg
                // resyncs past leading junk, but the browser side does not - it
                // fails the format probe and then sits there silently, which
                // looks exactly like a dead camera. A single PHP notice printed
                // with display_errors on is enough to cause it, so anything
                // buffered ahead of the stream is dropped here.
                for ($level = ob_get_level(); $level > 0; $level--) {
                    if (! @ob_end_clean()) {
                        break;
                    }
                }

                $startedAt = microtime(true);
                $process->start();

                // Only a stream that ended on its own is worth reporting - see
                // the stderr note below.
                $endedByUs = false;

                // Blocking iteration on purpose: the non-blocking variant
                // yields empty strings whenever no bytes are ready and spins a
                // core flat. A live camera delivers data continuously, so the
                // checks below still run several times a second.
                foreach ($process->getIterator(Process::ITER_SKIP_ERR) as $chunk) {
                    if ($chunk !== '') {
                        echo $chunk;
                        flush();
                    }

                    if (connection_aborted() === 1 || ($maxDuration > 0 && microtime(true) - $startedAt > $maxDuration)) {
                        $endedByUs = true;
                        break;
                    }
                }

                $stderr = trim($process->getErrorOutput());
                $process->stop(1);

                // Once we stop reading, ffmpeg fills stderr with broken pipe
                // errors - which is what every viewer closing a tab looks
                // like. Only an ffmpeg that quit by itself says anything
                // about the camera.
                if (! $endedByUs && $stderr !== '') {
                    Log::warning('Camera live stream ended unexpectedly', [
                        'device' => $device->getKey(),
                        'stderr' => $stderr,
                    ]);
                }
            },
            Response::HTTP_OK,
            [
                'Content-Type' => 'video/mp2t',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                // Stops nginx/Caddy from buffering the stream, which would
                // otherwise add seconds of latency or withhold it entirely.
                'X-Accel-Buffering' => 'no',
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function audioArguments(): array
    {
        if (! config('camera.live.audio', true)) {
            return ['-an'];
        }

        // -ac 1 because the microphones are mono anyway, and upmixing only
        // widens the stream. No -map: when a device sends no audio track at
        // all, ffmpeg simply produces none.
        return ['-c:a', 'aac', '-ar', '44100', '-ac', '1'];
    }
}
