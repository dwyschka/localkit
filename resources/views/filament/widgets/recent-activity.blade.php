<x-filament-widgets::widget>
    <style>
        .petkit-recent { position: relative; }
        .petkit-recent::before {
            content: '';
            position: absolute;
            left: 1.125rem;
            top: 0.375rem;
            bottom: 0.375rem;
            width: 2px;
            background: var(--gray-200);
        }
        .dark .petkit-recent::before { background: var(--gray-700); }
        .petkit-recent__item {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
            padding-bottom: 1.25rem;
        }
        .petkit-recent__item:last-child { padding-bottom: 0; }
        .petkit-recent__link { display: flex; align-items: flex-start; gap: 0.875rem; width: 100%; text-decoration: none; }
        .petkit-recent__node {
            position: relative;
            z-index: 1;
            flex: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: color-mix(in oklch, var(--color-500) 15%, transparent);
            color: var(--color-500);
        }
        .petkit-recent__node svg { width: 1.125rem; height: 1.125rem; }
        .petkit-recent__content { flex: 1 1 auto; min-width: 0; padding-top: 0.125rem; }
        .petkit-recent__title { font-weight: 600; font-size: 0.875rem; line-height: 1.4; color: var(--gray-950); }
        .dark .petkit-recent__title { color: var(--gray-100); }
        .petkit-recent__meta { color: var(--gray-500); font-size: 0.75rem; line-height: 1.5; margin-top: 0.25rem; }
        .petkit-recent__desc { color: var(--gray-500); font-size: 0.8125rem; line-height: 1.5; margin-top: 0.25rem; }
    </style>

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
