<x-filament-widgets::widget>
    @once
        {!! loadInlineStylesheet('css/petkit-event-counts.css') !!}
    @endonce

    <x-filament::section heading="Pet Activity by Day">
        <x-slot name="afterHeader">
            <x-filament::button
                color="gray"
                tag="a"
                href="{{ \App\Filament\Pages\ActivitiesPage::getUrl() }}"
                size="xs"
                icon="heroicon-m-arrow-right"
                icon-position="after"
            >
                {{ __('View all') }}
            </x-filament::button>
        </x-slot>

        @forelse ($dailyCounts as $date => $pets)
            @php
                $day = \Illuminate\Support\Carbon::parse(
                    $date,
                    config('app.timezone')
                );
                $dayStart = $day->copy()->startOfDay()->timestamp;
                $dayEnd = $day->copy()->endOfDay()->timestamp;

                $sortedPets = collect($pets)->sortBy(function ($entry) {
                    $pet = $entry['pet'] ?? null;
                    $name = $pet?->name ?? __('petkit.unknown');

                    return ($pet === null ? '1:' : '0:') . strtolower($name);
                });
            @endphp

            <div class="petkit-counts__day">
                <div class="petkit-counts__day-header">
                    <span class="petkit-counts__date">
                        {{
                            $day->isToday()
                                ? __('Today')
                                : (
                                    $day->isYesterday()
                                        ? __('Yesterday')
                                        : $day->translatedFormat('l, F j, Y')
                                )
                        }}
                    </span>

                    <x-filament::button
                        color="gray"
                        tag="a"
                        :href="\App\Filament\Pages\ActivitiesPage::getUrl([
                            'date_from' => $dayStart,
                            'date_to' => $dayEnd,
                        ])"
                        size="xs"
                        icon="heroicon-m-calendar"
                        icon-position="before"
                    >
                        {{ __('View Day') }}
                    </x-filament::button>
                </div>

                @foreach ($sortedPets as $entry)
                    @php
                        $pet = $entry['pet'] ?? null;

                        $petParam = $pet?->id
                            ?: \App\Filament\Pages\ActivitiesPage::UNKNOWN_PET;

                        $petName = $pet?->name
                            ?? __('petkit.unknown');
                    @endphp

                    <div class="petkit-counts__pet">
                        <a
                            href="{{ \App\Filament\Pages\ActivitiesPage::getUrl([
                                'pets' => [$petParam],
                                'date_from' => $dayStart,
                                'date_to' => $dayEnd,
                            ]) }}"
                            class="petkit-counts__pet-name hover:underline"
                            style="color:inherit;text-decoration:none;"
                            title="{{ __('Filter activities for :pet on :date', [
                                'pet' => $petName,
                                'date' => $date,
                            ]) }}"
                        >
                            {{ $petName }}
                        </a>

                        <div class="petkit-counts__badges">
                            @foreach ($entry['events'] as $type => $eventData)
                                @php
                                    $meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($type);
                                    $count = is_array($eventData) ? $eventData['count'] : $eventData;
                                    $humanDuration = is_array($eventData) ? ($eventData['human_duration'] ?? null) : null;
                                @endphp

                                <x-filament::badge
                                    :color="$meta['color']"
                                    :icon="$meta['icon']"
                                    tag="a"
                                    :href="\App\Filament\Pages\ActivitiesPage::getUrl([
                                        'pets' => [$petParam],
                                        'types' => [$type],
                                        'date_from' => $dayStart,
                                        'date_to' => $dayEnd,
                                    ])"
                                    style="cursor:pointer;"
                                    class="hover:opacity-80 transition-opacity"
                                >
                                    {{ $this->typeLabel($type) }}
                                    <span class="petkit-counts__count">&times;{{ $count }}</span>
                                    @if ($humanDuration)
                                        <span class="petkit-counts__duration">({{ $humanDuration }})</span>
                                    @endif
                                </x-filament::badge>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div
                style="
                    text-align:center;
                    padding:1rem 0;
                    color:var(--gray-500);
                    font-size:0.875rem;
                "
            >
                {{ __('No activities recorded yet.') }}
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
