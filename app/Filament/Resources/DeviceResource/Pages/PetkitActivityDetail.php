<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Pages\MediaPage;
use App\Filament\Resources\DeviceResource;
use App\Models\History;
use App\Models\MediaFile;
use App\Petkit\Storage\VideoRemuxer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PetkitActivityDetail extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.device-resource.pages.petkit-activity-detail';

    public History $history;

    public function mount(int|string $record, int|string $historyId): void
    {
        $this->record = $this->resolveRecord($record);

        $this->history = History::with('media')
            ->where('device_id', $this->record->id)
            ->findOrFail($historyId);
    }

    public function getTitle(): string
    {
        return $this->history->title();
    }

    /**
     * Recomputes duration from the segment metadata and re-encodes the
     * combined .ts (if it's still on disk) to MP4.
     *
     * A straight remux (-c copy) can't fix this: it's a stream copy, so
     * whatever discontinuous per-segment PTS/DTS got baked in when this was
     * combined with the old raw-byte-concat method (see VideoRemuxer::concatTs())
     * pass through mostly unchanged. Actually decoding and re-encoding
     * (VideoRemuxer::reencodeMp4()) forces the encoder to assign fresh,
     * monotonically increasing timestamps in presentation order, which is
     * the only way to retroactively fix an already-broken combined file
     * (the individual segments are long gone, so re-joining them isn't an
     * option here - see PetkitYumshareDual/PetkitEversweetUltra).
     */
    public function fixVideo(int $mediaId): void
    {
        $media = MediaFile::findOrFail($mediaId);

        if (empty($media->segments)) {
            Notification::make()->warning()->title('No segments to recompute from')->send();

            return;
        }

        $media->duration = collect($media->segments)->sum('duration');

        $disk = Storage::disk(MediaPage::DISK);
        $tsKey = VideoRemuxer::tsKey($media->object_key);

        if ($disk->exists($tsKey)) {
            try {
                $mp4Key = VideoRemuxer::mp4Key($tsKey);
                $disk->put($mp4Key, VideoRemuxer::reencodeMp4($disk->get($tsKey)));
                $media->object_key = $mp4Key;

                Notification::make()->success()->title('Duration recalculated, video re-encoded')->send();
            } catch (Throwable $e) {
                Log::warning('Manual video fix re-encode failed', ['media_id' => $media->id, 'error' => $e->getMessage()]);

                Notification::make()->warning()->title('Duration recalculated, but re-encode failed')->body($e->getMessage())->send();
            }
        } else {
            Notification::make()->warning()->title('Duration recalculated - original .ts is gone, video itself could not be re-remuxed')->send();
        }

        $media->save();

        $this->history->load('media');
    }

    public function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $bytes < 10 ? 1 : 0) . ' ' . $units[$i];
    }
}
