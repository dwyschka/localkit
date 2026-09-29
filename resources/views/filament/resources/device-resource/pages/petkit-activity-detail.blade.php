<x-filament-panels::page>
    @php($meta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($history->type))

@once
    {!! loadInlineStylesheet('css/petkit-activity-detail.css') !!}
@endonce


    <div style="margin-bottom:1rem;">
        <x-filament::button
            color="gray"
            icon="heroicon-m-arrow-left"
            tag="a"
            href="{{ \App\Filament\Pages\ActivitiesPage::getUrl(['devices' => [$this->record->id]]) }}"
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
            <div class="petkit-detail__value">{{ $history->created_at?->timezone(config('app.timezone'))?->isoFormat(\App\Models\History::DATETIME_WITH_SECONDS_FORMAT) }}</div>
        </div>
        <div class="petkit-detail__row">
            <div class="petkit-detail__label">Last updated</div>
            <div class="petkit-detail__value">{{ $history->updated_at?->timezone(config('app.timezone'))?->isoFormat(\App\Models\History::DATETIME_WITH_SECONDS_FORMAT) }}</div>
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
                            @if (!empty($clip->segments))
                                <tr>
                                    <td></td>
                                    <td colspan="7">
                                        <details>
                                            <summary style="cursor:pointer;color:var(--gray-500);">
                                                {{ count($clip->segments) }} merged segment(s)
                                            </summary>
                                            <table class="petkit-segments-table">
                                                <thead>
                                                    <tr>
                                                        <th>Segment file ID</th>
                                                        <th>Start</th>
                                                        <th>End</th>
                                                        <th>Duration</th>
                                                        <th>Size</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($clip->segments as $segment)
                                                        <tr>
                                                            <td><code>{{ $segment['fileId'] ?? '—' }}</code></td>
                                                            <td>{{ isset($segment['startTime']) ? \Illuminate\Support\Carbon::createFromTimestamp($segment['startTime'])->timezone(config('app.timezone'))->isoFormat('LTS') : '—' }}</td>
                                                            <td>{{ isset($segment['endTime']) ? \Illuminate\Support\Carbon::createFromTimestamp($segment['endTime'])->timezone(config('app.timezone'))->isoFormat('LTS') : '—' }}</td>
                                                            <td>{{ isset($segment['duration']) ? number_format($segment['duration'] / 1000, 1) . 's' : '—' }}</td>
                                                            <td>{{ $this->formatBytes($segment['size'] ?? null) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
