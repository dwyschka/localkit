<x-filament-panels::page>
    @php($histories = $this->getHistories())

    <style>
        .petkit-timeline { position: relative; padding-left: 2.75rem; }
        .petkit-timeline::before {
            content: '';
            position: absolute;
            left: 1.25rem;
            top: 0.25rem;
            bottom: 0.25rem;
            width: 2px;
            background: var(--gray-200);
        }
        .dark .petkit-timeline::before { background: var(--gray-700); }
        .petkit-timeline__item { position: relative; padding-bottom: 1.75rem; }
        .petkit-timeline__item:last-child { padding-bottom: 0; }
        .petkit-timeline__node {
            position: absolute;
            left: -1.75rem;
            top: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: color-mix(in oklch, var(--color-500) 15%, transparent);
            color: var(--color-500);
        }
        .petkit-timeline__node svg { width: 1.25rem; height: 1.25rem; }
        .petkit-timeline__title { font-weight: 600; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 0.375rem; }
        .petkit-timeline__info { color: var(--gray-400); }
        .petkit-timeline__info:hover { color: var(--primary-500); }
        .petkit-timeline__info svg { width: 1.1rem; height: 1.1rem; }
        .petkit-timeline__desc { color: var(--gray-500); font-size: 0.875rem; margin-top: 0.125rem; }
        .petkit-timeline__date { color: var(--gray-400); font-size: 0.75rem; margin-top: 0.25rem; }
        .petkit-timeline__media { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.625rem; }
        .petkit-timeline__media img,
        .petkit-timeline__media video {
            width: 10rem;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 0.5rem;
            background: var(--gray-950);
        }
    </style>

    @if ($histories->isEmpty())
        <x-filament::section>
            <div style="text-align:center;padding:1.5rem 0;color:var(--gray-500);font-size:0.875rem;">
                {{ __('No activities recorded yet.') }}
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="petkit-timeline">
                @foreach ($histories as $history)
                    @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($history->type))
                    <div class="petkit-timeline__item">
                        <span class="petkit-timeline__node" style="{{ \Filament\Support\get_color_css_variables($meta['color'], shades: [500]) }}">
                            @svg($meta['icon'])
                        </span>

                        <div class="petkit-timeline__title">
                            {{ $history->title() }}
                            @if (config('app.debug'))
                                <a
                                    href="{{ \App\Filament\Resources\DeviceResource::getUrl('activity', ['record' => $this->record, 'historyId' => $history->id]) }}"
                                    class="petkit-timeline__info"
                                    title="View details"
                                >
                                    @svg('heroicon-m-information-circle')
                                </a>
                            @endif
                        </div>
                        <div class="petkit-timeline__desc">{!! $history->message() !!}</div>
                        <div class="petkit-timeline__date">
                            {{ $history->created_at?->timezone('Europe/Berlin')?->format('F j, Y · H:i') }}
                        </div>

                        @php($listingMedia = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::mediaForListing($history->media))
                        @if ($listingMedia['image'] || $listingMedia['video'])
                            <div class="petkit-timeline__media">
                                @if ($listingMedia['image'])
                                    <a href="{{ route('media.file', ['fileId' => $listingMedia['image']->file_id]) }}" target="_blank">
                                        <img src="{{ route('media.file', ['fileId' => $listingMedia['image']->file_id]) }}" alt="Capture" loading="lazy" />
                                    </a>
                                @endif
                                @if ($listingMedia['video'])
                                    <video src="{{ route('media.file', ['fileId' => $listingMedia['video']->file_id]) }}" controls preload="none"></video>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;gap:1rem;flex-wrap:wrap;">
                <div style="font-size:0.8125rem;color:var(--gray-500);">
                    Showing {{ $histories->firstItem() }}–{{ $histories->lastItem() }} of {{ $histories->total() }}
                </div>
                <div style="display:flex;gap:0.5rem;">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-chevron-left"
                        wire:click="previousPage"
                        :disabled="$histories->onFirstPage()"
                    >
                        Previous
                    </x-filament::button>
                    <x-filament::button
                        color="gray"
                        size="sm"
                        icon="heroicon-m-chevron-right"
                        icon-position="after"
                        wire:click="nextPage"
                        :disabled="! $histories->hasMorePages()"
                    >
                        Next
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
