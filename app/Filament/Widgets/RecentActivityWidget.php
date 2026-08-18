<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DeviceResource;
use App\Filament\Resources\PetResource;
use App\Models\History;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Dashboard tile showing the most recent activity log entries across every
 * device and pet, so you don't have to open a specific pet/device's
 * Activities page just to see what happened last.
 */
class RecentActivityWidget extends Widget
{
    protected string $view = 'filament.widgets.recent-activity';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    /**
     * @return Collection<int, History>
     */
    protected function getHistories(): Collection
    {
        return History::query()
            ->with(['pet', 'device'])
            ->latest()
            ->limit(8)
            ->get();
    }

    protected function getViewData(): array
    {
        return [
            'histories' => $this->getHistories(),
        ];
    }

    public static function recordUrl(History $history): ?string
    {
        if ($history->pet_id) {
            return PetResource::getUrl('activities', ['record' => $history->pet_id]);
        }

        if ($history->device_id) {
            return DeviceResource::getUrl('activities', ['record' => $history->device_id]);
        }

        return null;
    }
}
