@props([
    'histories',
    'compact' => false,
    'showDeviceTag' => true,
    'showPetTag' => true,
    'emptyMessage' => __('No activities recorded yet.'),
])

@once
    {!! loadInlineStylesheet('css/petkit-timeline.css') !!}
@endonce

@if ($histories->isEmpty())
    <div style="text-align:center;padding:{{ $compact ? '1rem' : '2rem' }} 0;color:var(--gray-500);font-size:0.875rem;">
        {{ $emptyMessage }}
    </div>
@else
    <div class="petkit-timeline {{ $compact ? 'petkit-timeline--compact' : '' }}">
        @php($timezone = config('app.timezone'))
        @php($lastDate = null)
        @foreach ($histories as $history)
            @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($history->type))
            @php($eventDate = $history->created_at?->timezone($timezone))
            @php($dateKey = $eventDate?->toDateString())
            @if ($dateKey !== $lastDate)
                <div class="petkit-timeline__day">
                    {{ $eventDate?->isToday() ? __('Today') : ($eventDate?->isYesterday() ? __('Yesterday') : $eventDate?->translatedFormat('l, F j, Y')) }}
                </div>
                @php($lastDate = $dateKey)
            @endif
            <div class="petkit-timeline__item">
                @if ($history->type)
                    <a
                        href="{{ \App\Filament\Pages\ActivitiesPage::getUrl(['types' => [$history->type]]) }}"
                        class="petkit-timeline__node"
                        style="{{ \Filament\Support\get_color_css_variables($meta['color'], shades: [500]) }}"
                        title="{{ __('Filter by :type activities', ['type' => $history->title()]) }}"
                    >
                        @svg($meta['icon'])
                    </a>
                @else
                    <span class="petkit-timeline__node" style="{{ \Filament\Support\get_color_css_variables($meta['color'], shades: [500]) }}">
                        @svg($meta['icon'])
                    </span>
                @endif

                <div class="petkit-timeline__content">
                    <div class="petkit-timeline__body">
                        <div class="petkit-timeline__main">
                            <div class="petkit-timeline__title">
                                @if ($history->type)
                                    <a
                                        href="{{ \App\Filament\Pages\ActivitiesPage::getUrl(['types' => [$history->type]]) }}"
                                        class="petkit-timeline__title-link"
                                        title="{{ __('Filter by :type activities', ['type' => $history->title()]) }}"
                                    >
                                        {{ $history->title() }}
                                    </a>
                                @else
                                    {{ $history->title() }}
                                @endif

                                @if (config('app.debug') && $history->device_id)
                                    <a
                                        href="{{ \App\Filament\Resources\DeviceResource::getUrl('activity', ['record' => $history->device_id, 'historyId' => $history->id]) }}"
                                        class="petkit-timeline__info"
                                        title="View details"
                                    >
                                        @svg('heroicon-m-information-circle')
                                    </a>
                                @endif
                            </div>

                            <div class="petkit-timeline__tags">
                                @if ($showDeviceTag && $history->device)
                                    <a href="{{ \App\Filament\Pages\ActivitiesPage::getUrl(['devices' => [$history->device_id]]) }}" class="petkit-timeline__tag" title="{{ __('Device activities') }}">
                                        @svg('heroicon-m-rectangle-stack')
                                        {{ $history->device->name ?? $history->device->serial_number }}
                                    </a>
                                @endif

                                @if ($showPetTag && $history->isPetActivity())
                                    @php($petParam = $history->pet_id ?: \App\Filament\Pages\ActivitiesPage::UNKNOWN_PET)
                                    @php($petName = $history->pet?->name ?? __('petkit.unknown'))
                                    <a href="{{ \App\Filament\Pages\ActivitiesPage::getUrl(['pets' => [$petParam]]) }}" class="petkit-timeline__tag" title="{{ __('Pet activities') }}">
                                        @svg('heroicon-m-heart')
                                        {{ $petName }}
                                    </a>
                                @endif

                                @if ($history->eventDuration() > 0)
                                    <span class="petkit-timeline__tag">
                                        @svg('heroicon-m-clock')
                                        {{ $history->eventHumanDuration() }}
                                    </span>
                                @endif
                            </div>

                            <div class="petkit-timeline__desc">{!! $history->message() !!}</div>
                            <div class="petkit-timeline__date">
                                {{ $history->created_at?->timezone($timezone)?->isoFormat(\App\Models\History::DATETIME_FORMAT) }}
                                &middot;
                                {{ $history->created_at?->timezone($timezone)?->diffForHumans() }}
                            </div>
                        </div>

                        @if (! $compact)
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
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($histories instanceof \Illuminate\Contracts\Pagination\Paginator || $histories instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:1.5rem;gap:1rem;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">
                <div style="font-size:0.8125rem;color:var(--gray-500);">
                    Showing {{ $histories->firstItem() }}–{{ $histories->lastItem() }} of {{ $histories->total() }}
                </div>

                @if (isset($this->perPage))
                    <div style="display:flex;align-items:center;gap:0.375rem;">
                        <label style="font-size:0.8125rem;color:var(--gray-400);">Per page</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="perPage" style="font-size:0.8125rem;padding-top:0.25rem;padding-bottom:0.25rem;">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="all">{{ __('All') }}</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>
                @endif
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
    @endif
@endif
