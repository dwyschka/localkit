<x-filament-widgets::widget>
    <style>
        .petkit-counts__day { padding: 1rem 0; }
        .petkit-counts__day:first-child { padding-top: 0; }
        .petkit-counts__day + .petkit-counts__day { border-top: 1px solid var(--gray-200); }
        .dark .petkit-counts__day + .petkit-counts__day { border-top-color: var(--gray-700); }
        .petkit-counts__date {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-bottom: 0.75rem;
        }
        .petkit-counts__pet { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .petkit-counts__pet + .petkit-counts__pet { margin-top: 0.625rem; }
        .petkit-counts__pet-name { font-weight: 600; font-size: 0.875rem; min-width: 7rem; }
        .petkit-counts__badges { display: flex; flex-wrap: wrap; gap: 0.375rem; }
    </style>

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
