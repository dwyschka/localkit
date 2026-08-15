<x-filament-panels::page>
    @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($history->type))

    <style>
        .petkit-detail__row { display: flex; gap: 0.5rem; padding: 0.375rem 0; font-size: 0.875rem; }
        .petkit-detail__row + .petkit-detail__row { border-top: 1px solid var(--gray-200); }
        .dark .petkit-detail__row + .petkit-detail__row { border-top-color: var(--gray-700); }
        .petkit-detail__label { width: 9rem; flex-shrink: 0; color: var(--gray-500); }
        .petkit-detail__value { flex: 1; word-break: break-word; }
        .petkit-detail__icon svg { width: 1.25rem; height: 1.25rem; }
        .petkit-detail__params {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.8rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
            color: var(--gray-300);
            background: var(--gray-950);
            padding: 1rem;
            border-radius: 0.5rem;
            max-height: 24rem;
            overflow: auto;
        }
        .petkit-media-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
        .petkit-media-table th, .petkit-media-table td {
            text-align: left;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: top;
        }
        .dark .petkit-media-table th, .dark .petkit-media-table td { border-bottom-color: var(--gray-700); }
        .petkit-media-table th { color: var(--gray-500); font-weight: 500; }
        .petkit-media-table code { font-size: 0.75rem; }
        .petkit-media-preview img,
        .petkit-media-preview video {
            width: 8rem;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            border-radius: 0.375rem;
            background: var(--gray-950);
        }
    </style>

    <div style="margin-bottom:1rem;">
        <x-filament::button
            color="gray"
            icon="heroicon-m-arrow-left"
            tag="a"
            href="{{ \App\Filament\Resources\DeviceResource::getUrl('activities', ['record' => $this->record]) }}"
            size="sm"
        >
            Back to Activities
        </x-filament::button>
    </div>

    <x-filament::section>
        <x-slot name="heading">
            <span style="display:inline-flex;align-items:center;gap:0.5rem;">
                <span class="petkit-detail__icon" style="{{ \Filament\Support\get_color_css_variables($meta['color'], shades: [500]) }}; color: var(--color-500);">
                    @svg($meta['icon'])
                </span>
                {{ $history->title() }}
            </span>
        </x-slot>

        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Message</div>
            <div class="petkit-detail__value">{!! $history->message() ?: '—' !!}</div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Type</div>
            <div class="petkit-detail__value"><x-filament::badge :color="$meta['color']">{{ $history->type }}</x-filament::badge></div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Event ID</div>
            <div class="petkit-detail__value"><code>{{ $history->messageId }}</code></div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Pet</div>
            <div class="petkit-detail__value">{{ $history->pet?->name ?? '—' }}</div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Started</div>
            <div class="petkit-detail__value">{{ $history->created_at?->timezone('Europe/Berlin')?->format('F j, Y · H:i:s') }}</div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Last updated</div>
            <div class="petkit-detail__value">{{ $history->updated_at?->timezone('Europe/Berlin')?->format('F j, Y · H:i:s') }}</div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Duration</div>
            <div class="petkit-detail__value">{{ $history->eventDuration() }} seconds</div>
        </div>
    </x-filament::section>

    <x-filament::section style="margin-top:1.5rem;">
        <x-slot name="heading">Raw parameters</x-slot>
        <pre class="petkit-detail__params">{{ json_encode($history->parameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </x-filament::section>

    <x-filament::section style="margin-top:1.5rem;">
        <x-slot name="heading">Files ({{ $history->media->count() }})</x-slot>

        @if ($history->media->isEmpty())
            <div style="text-align:center;padding:1.5rem 0;color:var(--gray-500);font-size:0.875rem;">
                No files linked to this event.
            </div>
        @else
            <div style="overflow-x:auto;">
                <table class="petkit-media-table">
                    <thead>
                        <tr>
                            <th>Preview</th>
                            <th>File ID</th>
                            <th>Module</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Duration</th>
                            <th>Decrypted</th>
                            <th>Object key</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($history->media as $clip)
                            <tr>
                                <td class="petkit-media-preview">
                                    @if ($clip->isVideo())
                                        <video src="{{ route('media.file', ['fileId' => $clip->file_id]) }}" controls preload="none"></video>
                                    @else
                                        <a href="{{ route('media.file', ['fileId' => $clip->file_id]) }}" target="_blank">
                                            <img src="{{ route('media.file', ['fileId' => $clip->file_id]) }}" alt="Capture" loading="lazy" />
                                        </a>
                                    @endif
                                </td>
                                <td><code>{{ $clip->file_id }}</code></td>
                                <td>{{ $clip->module_type }}</td>
                                <td>{{ $clip->file_type }}</td>
                                <td>{{ $this->formatBytes($clip->size) }}</td>
                                <td>{{ $clip->duration ? number_format($clip->duration / 1000, 1) . 's' : '—' }}</td>
                                <td>
                                    <x-filament::icon
                                        :icon="$clip->decrypted ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'"
                                        style="width:1.1rem;height:1.1rem;color:{{ $clip->decrypted ? 'var(--success-500)' : 'var(--gray-400)' }};"
                                    />
                                </td>
                                <td><code>{{ $clip->object_key }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
