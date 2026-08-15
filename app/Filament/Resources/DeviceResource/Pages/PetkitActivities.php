<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\History;
use App\Models\MediaFile;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

class PetkitActivities extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = DeviceResource::class;

    protected string $view = 'filament.resources.device-resource.pages.petkit-activities';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Activities — ' . ($this->record->name ?? $this->record->serial_number);
    }

    /**
     * @return LengthAwarePaginator<int, History>
     */
    public function getHistories(): LengthAwarePaginator
    {
        return $this->record->histories()->with('media')->latest()->paginate(10);
    }

    /**
     * Recordings that exist on disk but whose event_id doesn't match any
     * History row's messageId - e.g. the pet_detect/drink_start MQTT event
     * never arrived, or arrived before that topic's History::create() was
     * deployed. Surfaced separately so a clip is never just invisible
     * because the correlation happened to miss.
     *
     * @return Collection<int, MediaFile>
     */
    public function getUnlinkedMedia(): Collection
    {
        $linkedEventIds = History::where('device_id', $this->record->id)
            ->whereNotNull('messageId')
            ->pluck('messageId');

        return MediaFile::where('device_id', $this->record->id)
            ->whereNotNull('event_id')
            ->whereNotIn('event_id', $linkedEventIds)
            ->latest()
            ->limit(20)
            ->get();
    }

    /**
     * Display order for the media thumbnails in the timeline/unlinked
     * listings (not the per-activity detail page, which shows everything in
     * a table) - the still preview image first, then the video.
     *
     * @param Collection<int, MediaFile> $media
     * @return Collection<int, MediaFile>
     */
    public static function sortMediaForListing(Collection $media): Collection
    {
        return $media->sortBy(fn (MediaFile $clip) => match ($clip->module_type) {
            'EVENT_PREVIEW' => 0,
            'CLOUD_STORAGE' => 1,
            default => 2,
        })->values();
    }

    /**
     * Heroicon + colour for a given history type.
     *
     * @return array{icon: string, color: string}
     */
    public static function typeMeta(?string $type): array
    {
        return match ($type) {
            'IN_USE' => ['icon' => 'heroicon-m-exclamation-triangle', 'color' => 'warning'],
            'CLEANING' => ['icon' => 'heroicon-m-arrow-path-rounded-square', 'color' => 'info'],
            'MAINTENANCE' => ['icon' => 'heroicon-m-wrench-screwdriver', 'color' => 'info'],
            'ERROR' => ['icon' => 'heroicon-m-exclamation-circle', 'color' => 'danger'],
            'EAT' => ['icon' => 'heroicon-m-cake', 'color' => 'success'],
            'DRINK' => ['icon' => 'heroicon-m-beaker', 'color' => 'info'],
            'DETECT' => ['icon' => 'heroicon-m-camera', 'color' => 'primary'],
            default => ['icon' => 'heroicon-m-bolt', 'color' => 'gray'],
        };
    }
}
