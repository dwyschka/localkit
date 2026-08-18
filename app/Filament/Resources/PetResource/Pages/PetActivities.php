<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Resources\PetResource;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

/**
 * Mirrors DeviceResource\Pages\PetkitActivities, scoped to a Pet instead of
 * a Device - every History row this pet was matched to via the async
 * pet_discern event (see Device::mergeHistory()), across every device that
 * recognized it. Reuses PetkitActivities' static helpers (typeMeta,
 * mediaForListing) since they're pure functions that don't depend on which
 * kind of record owns the history.
 */
class PetActivities extends Page
{
    use InteractsWithRecord;
    use WithPagination;

    protected static string $resource = PetResource::class;

    protected string $view = 'filament.resources.pet-resource.pages.pet-activities';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string
    {
        return 'Activities — ' . $this->record->name;
    }

    /**
     * @return LengthAwarePaginator<int, \App\Models\History>
     */
    public function getHistories(): LengthAwarePaginator
    {
        return $this->record->histories()->with(['media', 'device'])->latest()->paginate(10);
    }
}
