<x-filament-panels::page>
    @php($files = $this->availableFiles())
    @php($content = $this->getLogContent())

    <style>
        .petkit-log__pre {
            margin: 0;
            padding: 1rem;
            max-height: 65vh;
            overflow: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 0.8rem;
            line-height: 1.55;
            white-space: pre-wrap;
            word-break: break-word;
            color: var(--gray-300);
            background: var(--gray-950);
            border-radius: 0.5rem;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Application logs</x-slot>
        <x-slot name="description">Showing the latest {{ $this->lines }} lines from <code>storage/logs</code>.</x-slot>

        @if ($files->isEmpty())
            <div style="text-align:center;padding:1.5rem 0;color:var(--gray-500);font-size:0.875rem;">
                No log files found.
            </div>
        @else
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;">
                <label style="font-size:0.875rem;color:var(--gray-400);">File</label>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="logFile">
                        @foreach ($files as $name => $path)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <x-filament::button
                    color="gray"
                    icon="heroicon-m-arrow-path"
                    wire:click="$refresh"
                    size="sm"
                >
                    Refresh
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    icon="heroicon-m-arrow-down-tray"
                    wire:click="download"
                    size="sm"
                >
                    Download
                </x-filament::button>
            </div>

            @if (trim($content) === '')
                <div style="text-align:center;padding:1.5rem 0;color:var(--gray-500);font-size:0.875rem;">
                    This log file is empty.
                </div>
            @else
                <pre class="petkit-log__pre">{{ $content }}</pre>
            @endif
        @endif
    </x-filament::section>
</x-filament-panels::page>
