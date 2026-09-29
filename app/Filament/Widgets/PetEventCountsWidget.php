<?php

namespace App\Filament\Widgets;

use App\Models\History;
use App\Petkit\DeviceStates;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Dashboard tile showing, per pet and per day, how many times each pet activity
 * type was logged - a quick day-by-day activity count rather than a single
 * all-time total.
 *
 * Events without an associated pet are grouped together as "Unknown".
 * Device-level states are excluded using the existing DeviceStates enum.
 */
class PetEventCountsWidget extends Widget
{
    protected string $view = 'filament.widgets.pet-event-counts';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    private const DAYS = 3;

    /**
     * @return Collection<string, Collection<int, array{
     *     pet: \App\Models\Pet|null,
     *     events: Collection<string, array{
     *         count: int,
     *         total_duration: int,
     *         human_duration: string|null
     *     }>
     * }>>
     */
    protected function getDailyCounts(): Collection
    {
        $timezone = (string) config('app.timezone');

        $since = now($timezone)
            ->subDays(self::DAYS - 1)
            ->startOfDay()
            ->setTimezone('UTC');

        return History::query()
            // This widget is for pet activity, so exclude device-level states.
            ->whereNotIn('type', DeviceStates::values())
            ->where('created_at', '>=', $since)
            ->with('pet:id,name')
            ->get()
            ->groupBy(
                fn (History $history) =>
                    $history->created_at
                        ->timezone($timezone)
                        ->toDateString()
            )
            ->sortKeysDesc()
            ->map(
                fn (Collection $dayHistories) =>
                    $dayHistories
                        ->groupBy('pet_id')
                        ->map(fn (Collection $petHistories) => [
                            'pet' => $petHistories->first()->pet,

                            // Keep event types in a deterministic alphabetical order.
                            'events' => $petHistories
                                ->groupBy('type')
                                ->map(function (Collection $typeHistories): array {
                                    $totalDuration = (int) $typeHistories->sum(fn (History $h): int => $h->eventDuration());

                                    return [
                                        'count' => $typeHistories->count(),
                                        'total_duration' => $totalDuration,
                                        'human_duration' => $totalDuration > 0 ? History::formatHumanDuration($totalDuration) : null,
                                    ];
                                })
                                ->sortBy(
                                    fn (array $eventData, string $type) =>
                                        strtolower(History::typeTitle($type))
                                ),
                        ])
                        // Sort pets in an alphabetical order, with "Unknown" pets (null) at the end of the list.
                        ->sortBy(
                            fn (array $entry) => sprintf(
                                '%d:%s',
                                $entry['pet'] === null ? 1 : 0,
                                strtolower($entry['pet']?->name ?? '')
                            )
                        )
                        ->values()
            );
    }

    protected function getViewData(): array
    {
        return [
            'dailyCounts' => $this->getDailyCounts(),
        ];
    }

    public static function typeLabel(string $type): string
    {
        return History::typeTitle($type);
    }
}
