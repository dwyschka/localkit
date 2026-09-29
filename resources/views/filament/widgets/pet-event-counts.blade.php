<x-filament-widgets::widget>
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('petkit-event-counts') }}">

    <x-filament::section heading="Pet Activity by Day">
        @forelse ($dailyCounts as $date => $pets)
            @php($day = \Illuminate\Support\Carbon::parse($date, config('app.timezone')))
            <div class="petkit-counts__day">
                <div class="petkit-counts__date">
                    {{ $day->isToday() ? __('Today') : ($day->isYesterday() ? __('Yesterday') : $day->translatedFormat('l, F j, Y')) }}
                </div>

                @foreach ($pets as $entry)
                    <div class="petkit-counts__pet">
                        <span class="petkit-counts__pet-name">{{ $entry['pet']->name }}</span>
                        <div class="petkit-counts__badges">
                            @foreach ($entry['events'] as $type => $count)
                                @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($type))
                                <x-filament::badge :color="$meta['color']" :icon="$meta['icon']">
                                    {{ $this->typeLabel($type) }} &times; {{ $count }}
                                </x-filament::badge>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div style="text-align:center;padding:1rem 0;color:var(--gray-500);font-size:0.875rem;">
                {{ __('No activities recorded yet.') }}
            </div>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
