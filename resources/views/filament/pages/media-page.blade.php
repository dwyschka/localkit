<x-filament-panels::page>
    @php($directories = $this->directories())
    @php($files = $this->files())

    <style>
        .petkit-media__row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
        }
        .petkit-media__row:hover {
            background: var(--gray-500-10, rgba(127, 127, 127, 0.08));
        }
        .petkit-media__name {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.875rem;
        }
        .petkit-media__meta {
            font-size: 0.75rem;
            color: var(--gray-500);
            white-space: nowrap;
        }
        .petkit-media__breadcrumbs {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        .petkit-media__breadcrumbs button {
            color: var(--primary-500);
        }
        .petkit-media__breadcrumbs button:hover {
            text-decoration: underline;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Media</x-slot>
        <x-slot name="description">Browse files on the object storage disk.</x-slot>

        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap;">
            <x-filament::button
                color="gray"
                icon="heroicon-m-arrow-path"
                wire:click="$refresh"
                size="sm"
            >
                Refresh
            </x-filament::button>
        </div>

        <div class="petkit-media__breadcrumbs" style="margin-bottom:0.75rem;">
            @foreach ($this->breadcrumbs() as [$label, $crumbPath])
                @if (!$loop->first)
                    <span>/</span>
                @endif
                <button type="button" wire:click="open('{{ $crumbPath }}')">{{ $label }}</button>
            @endforeach
        </div>

        @if ($error)
            <div style="text-align:center;padding:1.5rem 0;color:var(--danger-500);font-size:0.875rem;">
                {{ $error }}
            </div>
        @elseif ($directories->isEmpty() && $files->isEmpty())
            <div style="text-align:center;padding:1.5rem 0;color:var(--gray-500);font-size:0.875rem;">
                @if ($path !== '')
                    This folder is empty.
                @else
                    No files found on this disk.
                @endif
            </div>
        @else
            <div>
                @if ($path !== '')
                    <div class="petkit-media__row">
                        <x-filament::icon icon="heroicon-m-arrow-up" style="height:1.25rem;width:1.25rem;color:var(--gray-500);" />
                        <button type="button" wire:click="up" class="petkit-media__name" style="text-align:left;">..</button>
                    </div>
                @endif

                @foreach ($directories as $directory)
                    <div class="petkit-media__row">
                        <x-filament::icon icon="heroicon-m-folder" style="height:1.25rem;width:1.25rem;color:var(--gray-500);" />
                        <button type="button" wire:click="open('{{ $directory }}')" class="petkit-media__name" style="text-align:left;">
                            {{ basename($directory) }}
                        </button>
                    </div>
                @endforeach

                @foreach ($files as $file)
                    <div class="petkit-media__row">
                        <x-filament::icon icon="heroicon-m-document" style="height:1.25rem;width:1.25rem;color:var(--gray-500);" />
                        <span class="petkit-media__name">{{ $file['name'] }}</span>
                        <span class="petkit-media__meta">{{ $this->formatBytes($file['size']) }}</span>
                        <span class="petkit-media__meta">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->diffForHumans() }}</span>
                        <x-filament::icon-button
                            icon="heroicon-m-arrow-down-tray"
                            tag="a"
                            href="{{ $this->downloadUrl($file['path']) }}"
                            label="Download"
                            size="sm"
                        />
                        <x-filament::icon-button
                            icon="heroicon-m-trash"
                            color="danger"
                            wire:click="delete('{{ $file['path'] }}')"
                            wire:confirm="Delete {{ $file['name'] }}? This cannot be undone."
                            label="Delete"
                            size="sm"
                        />
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
