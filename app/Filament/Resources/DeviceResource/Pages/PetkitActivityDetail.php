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
     * Recomputes duration from the segment metadata and re-remuxes the
     * combined .ts (if it's still on disk) to MP4 with the current
     * VideoRemuxer settings.
     *
     * Honest limitation: the individual segments are deleted once merged,
     * so this can't retroactively fix timestamp discontinuities baked into
     * a .ts that was combined with the old raw-byte-concat method (see
     * VideoRemuxer::concatTs()) - only duration gets corrected in that case.
     * For a .ts combined with the current concat-demuxer method, re-remuxing
     * picks up any VideoRemuxer improvements (e.g. the AAC bitstream filter)
     * made since it was first converted.
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
                $disk->put($mp4Key, VideoRemuxer::toMp4($disk->get($tsKey)));
                $media->object_key = $mp4Key;

                Notification::make()->success()->title('Duration recalculated, video re-remuxed')->send();
            } catch (Throwable $e) {
                Log::warning('Manual video fix remux failed', ['media_id' => $media->id, 'error' => $e->getMessage()]);

                Notification::make()->warning()->title('Duration recalculated, but remux failed')->body($e->getMessage())->send();
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
