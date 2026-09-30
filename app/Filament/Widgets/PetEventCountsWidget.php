<?php

namespace App\Filament\Widgets;

use App\Models\History;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Dashboard tile showing, per pet and per day, how many times each event
 * type (EAT, DRINK, IN_USE, ...) was logged - a quick day-by-day activity
 * count rather than a single all-time total.
 */
class PetEventCountsWidget extends Widget
{
    protected string $view = 'filament.widgets.pet-event-counts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    private const DAYS = 3;

    /**
     * @return Collection<string, Collection<int, array{pet: \App\Models\Pet, events: Collection<string, int>}>>
     */
    protected function getDailyCounts(): Collection
    {
        $since = now()->subDays(self::DAYS - 1)->startOfDay();

        return History::query()
            ->whereNotNull('pet_id')
            ->where('created_at', '>=', $since)
            ->with('pet:id,name')
            ->get()
            ->groupBy(fn (History $history) => $history->created_at->timezone(config('app.timezone'))->toDateString())
            ->sortKeysDesc()
            ->map(fn (Collection $dayHistories) => $dayHistories
                ->groupBy('pet_id')
                ->filter(fn (Collection $petHistories) => $petHistories->first()->pet !== null)
                ->map(fn (Collection $petHistories) => [
                    'pet' => $petHistories->first()->pet,
                    'events' => $petHistories->groupBy('type')->map->count(),
                ])
                ->sortBy(fn (array $entry) => $entry['pet']->name)
                ->values());
    }

    protected function getViewData(): array
    {
        return [
            'dailyCounts' => $this->getDailyCounts(),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return (new History(['type' => $type]))->title();
    }
}
