<x-filament-widgets::widget>
    {!! loadInlineStylesheet('css/petkit-recent-activity.css') !!}

    <x-filament::section heading="Last Activity">
        @if ($histories->isEmpty())
            <div style="text-align:center;padding:1rem 0;color:var(--gray-500);font-size:0.875rem;">
                {{ __('No activities recorded yet.') }}
            </div>
        @else
            <div class="petkit-recent">
                @foreach ($histories as $history)
                    @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($history->type))
                    @php($url = $this->recordUrl($history))
                    <div class="petkit-recent__item">
                        @php($linkTag = $url ? 'a' : 'div')
                        <{{ $linkTag }} @if($url) href="{{ $url }}" @endif class="petkit-recent__link">
                            <span class="petkit-recent__node" style="{{ \Filament\Support\get_color_css_variables($meta['color'], shades: [500]) }}">
                                @svg($meta['icon'])
                            </span>

                            <div class="petkit-recent__content">
                                <div class="petkit-recent__title">{{ $history->title() }}</div>
                                <div class="petkit-recent__meta">
                                    {{ $history->pet?->name ?? $history->device?->name ?? $history->device?->serial_number ?? __('petkit.unknown') }}
                                    &middot;
                                    {{ $history->created_at?->timezone(config('app.timezone'))?->diffForHumans() }}
                                </div>
                                <div class="petkit-recent__desc">{!! $history->message() !!}</div>
                            </div>
                        </{{ $linkTag }}>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
