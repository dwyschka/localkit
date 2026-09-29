{{-- Shared Activity Filter Toolbar across Activities and Timeline Scrubber pages --}}
<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;flex-wrap:wrap;">
    {{-- Device Multi-Select Dropdown --}}
    <div
        wire:key="filter-dropdown-devices"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-rectangle-stack"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ __('Devices') }}
            @if (count($this->deviceIds) > 0)
                <span class="petkit-filter-btn-badge">
                    {{ count($this->deviceIds) }}
                </span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-64 overflow-y-auto rounded-lg bg-white p-2 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:14rem;max-height:16rem;z-index:30;"
        >
            @foreach ($this->availableDevices() as $device)
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;font-size:0.875rem;cursor:pointer;border-radius:0.375rem;transition:background 0.1s;" class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <input
                        type="checkbox"
                        value="{{ $device->id }}"
                        wire:model.live="deviceIds"
                        class="fi-checkbox-input rounded border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 checked:ring-0 focus:ring-2 focus:ring-offset-0 disabled:pointer-events-none disabled:opacity-70 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-primary-600 dark:text-primary-500"
                    />
                    <span>{{ $device->name ?? $device->serial_number }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Pet Multi-Select Dropdown --}}
    <div
        wire:key="filter-dropdown-pets"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-heart"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ __('Pets') }}
            @if (count($this->petIds) > 0)
                <span class="petkit-filter-btn-badge">
                    {{ count($this->petIds) }}
                </span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-64 overflow-y-auto rounded-lg bg-white p-2 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:14rem;max-height:16rem;z-index:30;"
        >
            <label style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;font-size:0.875rem;cursor:pointer;border-radius:0.375rem;transition:background 0.1s;" class="hover:bg-gray-100 dark:hover:bg-gray-800">
                <input
                    type="checkbox"
                    value="{{ $this::UNKNOWN_PET }}"
                    wire:model.live="petIds"
                    class="fi-checkbox-input rounded border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 checked:ring-0 focus:ring-2 focus:ring-offset-0 disabled:pointer-events-none disabled:opacity-70 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-primary-600 dark:text-primary-500"
                />
                <span>{{ __('Unknown') }}</span>
            </label>
            @foreach ($this->availablePets() as $pet)
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;font-size:0.875rem;cursor:pointer;border-radius:0.375rem;transition:background 0.1s;" class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <input
                        type="checkbox"
                        value="{{ $pet->id }}"
                        wire:model.live="petIds"
                        class="fi-checkbox-input rounded border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 checked:ring-0 focus:ring-2 focus:ring-offset-0 disabled:pointer-events-none disabled:opacity-70 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-primary-600 dark:text-primary-500"
                    />
                    <span>{{ $pet->name }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Type Multi-Select Dropdown --}}
    <div
        wire:key="filter-dropdown-types"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-tag"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ __('Types') }}
            @if (count($this->types) > 0)
                <span class="petkit-filter-btn-badge">
                    {{ count($this->types) }}
                </span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-64 overflow-y-auto rounded-lg bg-white p-2 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:14rem;max-height:16rem;z-index:30;"
        >
            @foreach ($this->availableTypes() as $key => $label)
                @php
                    $typeMeta = \App\Filament\Resources\DeviceResource\Pages\PetkitActivities::typeMeta($key);
                    $typeIcon = $typeMeta['icon'];
                @endphp
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;font-size:0.875rem;cursor:pointer;border-radius:0.375rem;transition:background 0.1s;" class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <input
                        type="checkbox"
                        value="{{ $key }}"
                        wire:model.live="types"
                        class="fi-checkbox-input rounded border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 checked:ring-0 focus:ring-2 focus:ring-offset-0 disabled:pointer-events-none disabled:opacity-70 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-primary-600 dark:text-primary-500"
                    />
                    <x-filament::icon :icon="$typeIcon" style="width:1rem;height:1rem;color:var(--gray-400);" />
                    <span class="text-gray-900 dark:text-gray-100">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Media Type Multi-Select Dropdown --}}
    <div
        wire:key="filter-dropdown-media"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-photo"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ __('Media') }}
            @if (count($this->mediaTypes) > 0)
                <span class="petkit-filter-btn-badge">
                    {{ count($this->mediaTypes) }}
                </span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-64 overflow-y-auto rounded-lg bg-white p-2 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:14rem;max-height:16rem;z-index:30;"
        >
            @foreach ($this->availableMediaTypes() as $key => $label)
                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.5rem;font-size:0.875rem;cursor:pointer;border-radius:0.375rem;transition:background 0.1s;" class="hover:bg-gray-100 dark:hover:bg-gray-800">
                    <input
                        type="checkbox"
                        value="{{ $key }}"
                        wire:model.live="mediaTypes"
                        class="fi-checkbox-input rounded border-none bg-white shadow-sm ring-1 ring-gray-950/10 transition duration-75 checked:ring-0 focus:ring-2 focus:ring-offset-0 disabled:pointer-events-none disabled:opacity-70 dark:bg-white/5 dark:ring-white/20 text-primary-600 focus:ring-primary-600 dark:text-primary-500"
                    />
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Date Filter Dropdown (Presets & Custom Range) --}}
    <div
        wire:key="filter-dropdown-date"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-calendar"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ $this->activeDateFilterLabel() ?? __('Date') }}
            @if ($this->dateFrom || $this->dateTo)
                <span style="display:inline-flex;align-items:center;justify-content:center;margin-left:0.375rem;width:0.5rem;height:0.5rem;border-radius:9999px;background:var(--primary-500);"></span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-[30rem] overflow-y-auto rounded-lg bg-white p-3 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:20rem;max-height:30rem;z-index:30;"
        >
            {{-- Quick Presets --}}
            <div style="margin-bottom:0.75rem;padding-bottom:0.75rem;border-bottom:1px solid var(--gray-200);" class="dark:border-gray-800">
                <div style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.375rem;">
                    {{ __('Quick Presets') }}
                </div>
                <div style="display:flex;flex-direction:column;gap:0.375rem;">
                    <button
                        type="button"
                        wire:click="removeDateFilter"
                        x-on:click="open = false"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ (! $this->dateFrom && ! $this->dateTo) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __('All Dates (Any Date)') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setDatePreset('{{ $this::PRESET_TODAY }}')"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ $this->isDatePresetActive($this::PRESET_TODAY) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __('Today') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setDatePreset('{{ $this::PRESET_YESTERDAY }}')"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ $this->isDatePresetActive($this::PRESET_YESTERDAY) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __('Yesterday') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setDatePreset('{{ $this::PRESET_LAST_7_DAYS }}')"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ $this->isDatePresetActive($this::PRESET_LAST_7_DAYS) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __('Last 7 Days') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setDatePreset('{{ $this::PRESET_LAST_30_DAYS }}')"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ $this->isDatePresetActive($this::PRESET_LAST_30_DAYS) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __('Last 30 Days') }}
                    </button>
                </div>
            </div>

            {{-- Custom Date Range --}}
            <div>
                <div style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">
                    {{ __('Custom Range') }}
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <div style="flex:1;">
                        <label style="font-size:0.75rem;color:var(--gray-500);display:block;margin-bottom:0.125rem;">{{ __('From') }}</label>
                        <input
                            type="date"
                            value="{{ $this->customDateFrom }}"
                            wire:change="setCustomDateFrom($event.target.value)"
                            class="fi-input block w-full rounded-lg border-none bg-white py-1.5 px-2 text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.75rem;color:var(--gray-500);display:block;margin-bottom:0.125rem;">{{ __('To') }}</label>
                        <input
                            type="date"
                            value="{{ $this->customDateTo }}"
                            wire:change="setCustomDateTo($event.target.value)"
                            class="fi-input block w-full rounded-lg border-none bg-white py-1.5 px-2 text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </div>
                </div>
                @if ($this->dateFrom || $this->dateTo)
                    <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--gray-200);display:flex;gap:0.375rem;align-items:center;justify-content:flex-end;" class="dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="removeDateFilter"
                            x-on:click="open = false"
                            style="padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:0.375rem;border:1px solid var(--gray-300);background:var(--gray-100);cursor:pointer;white-space:nowrap;"
                            class="dark:border-gray-700 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                            title="{{ __('Clear date range') }}"
                        >
                            {{ __('Clear Date') }}
                        </button>
                        @if ($this->timeFrom || $this->timeTo)
                            <button
                                type="button"
                                wire:click="removeDateTimeFilter"
                                x-on:click="open = false"
                                style="padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:0.375rem;border:1px solid var(--gray-300);background:var(--gray-100);cursor:pointer;white-space:nowrap;"
                                class="dark:border-gray-700 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                                title="{{ __('Clear both date and time filters') }}"
                            >
                                {{ __('Clear Date & Time') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Time-of-Day Filter Dropdown --}}
    <div
        wire:key="filter-dropdown-time"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-clock"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ $this->activeTimeFilterLabel() ?? __('Time') }}
            @if ($this->timeFrom || $this->timeTo)
                <span style="display:inline-flex;align-items:center;justify-content:center;margin-left:0.375rem;width:0.5rem;height:0.5rem;border-radius:9999px;background:var(--primary-500);"></span>
            @endif
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-[30rem] overflow-y-auto rounded-lg bg-white p-3 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:18rem;max-height:30rem;z-index:30;"
        >
            {{-- Time of Day Presets --}}
            <div style="margin-bottom:0.75rem;padding-bottom:0.75rem;border-bottom:1px solid var(--gray-200);" class="dark:border-gray-800">
                <div style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.375rem;">
                    {{ __('Time of Day') }}
                </div>
                <div style="display:flex;flex-direction:column;gap:0.375rem;">
                    <button
                        type="button"
                        wire:click="removeTimeFilter"
                        x-on:click="open = false"
                        style="display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ (! $this->timeFrom && ! $this->timeTo) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        <span>{{ __('All Day (Any Time)') }}</span>
                        <span style="font-size:0.75rem;opacity:0.85;font-family:ui-monospace, monospace;">00:00 – 23:59</span>
                    </button>
                    @php
                        $timePresets = [
                            $this::PRESET_TIME_EARLY_MORNING => ['title' => __('Early Morning'), 'from' => '05:00', 'to' => '08:00'],
                            $this::PRESET_TIME_MORNING => ['title' => __('Morning'), 'from' => '08:00', 'to' => '12:00'],
                            $this::PRESET_TIME_AFTERNOON => ['title' => __('Afternoon'), 'from' => '12:00', 'to' => '17:00'],
                            $this::PRESET_TIME_EVENING => ['title' => __('Evening'), 'from' => '17:00', 'to' => '21:00'],
                            $this::PRESET_TIME_NIGHT => ['title' => __('Night'), 'from' => '21:00', 'to' => '06:00'],
                        ];
                    @endphp
                    @foreach ($timePresets as $presetKey => $presetData)
                        @php
                            $presetTitle = $presetData['title'];
                            $fromTime = $presetData['from'];
                            $toTime = $presetData['to'];
                        @endphp
                        <button
                            type="button"
                            wire:click="setTimePreset('{{ $presetKey }}')"
                            style="display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                            class="border {{ $this->isTimePresetActive($presetKey) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                        >
                            <span>{{ $presetTitle }}</span>
                            <span
                                style="font-size:0.75rem;opacity:0.85;font-family:ui-monospace, monospace;"
                                x-data="{ from: '{{ $fromTime }}', to: '{{ $toTime }}' }"
                                x-text="(() => {
                                    const fmt = (t) => {
                                        const [h, m] = t.split(':').map(Number);
                                        const d = new Date(); d.setHours(h, m, 0, 0);
                                        return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                                    };
                                    return fmt(from) + ' – ' + fmt(to);
                                })()"
                            >{{ $fromTime }} – {{ $toTime }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Custom Time Range --}}
            <div>
                <div style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">
                    {{ __('Custom Time Window') }}
                </div>
                <div style="display:flex;gap:0.5rem;align-items:center;">
                    <div style="flex:1;">
                        <label style="font-size:0.75rem;color:var(--gray-500);display:block;margin-bottom:0.125rem;">{{ __('From') }}</label>
                        <input
                            type="time"
                            wire:model.live="timeFrom"
                            class="fi-input block w-full rounded-lg border-none bg-white py-1.5 px-2.5 text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.75rem;color:var(--gray-500);display:block;margin-bottom:0.125rem;">{{ __('To') }}</label>
                        <input
                            type="time"
                            wire:model.live="timeTo"
                            class="fi-input block w-full rounded-lg border-none bg-white py-1.5 px-2.5 text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                        />
                    </div>
                </div>
                @if ($this->timeFrom || $this->timeTo)
                    <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--gray-200);display:flex;gap:0.375rem;align-items:center;justify-content:flex-end;" class="dark:border-gray-800">
                        <button
                            type="button"
                            wire:click="removeTimeFilter"
                            x-on:click="open = false"
                            style="padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:0.375rem;border:1px solid var(--gray-300);background:var(--gray-100);cursor:pointer;white-space:nowrap;"
                            class="dark:border-gray-700 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                            title="{{ __('Clear time window') }}"
                        >
                            {{ __('Clear Time') }}
                        </button>
                        @if ($this->dateFrom || $this->dateTo)
                            <button
                                type="button"
                                wire:click="removeDateTimeFilter"
                                x-on:click="open = false"
                                style="padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:0.375rem;border:1px solid var(--gray-300);background:var(--gray-100);cursor:pointer;white-space:nowrap;"
                                class="dark:border-gray-700 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                                title="{{ __('Clear both date and time filters') }}"
                            >
                                {{ __('Clear Date & Time') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Blank Out Date & Time Shortcut Button (Visible when both Date and Time filters are set) --}}
    @if (($this->dateFrom || $this->dateTo) && ($this->timeFrom || $this->timeTo))
        <x-filament::button
            color="gray"
            size="sm"
            variant="ghost"
            icon="heroicon-m-calendar-days"
            wire:click="removeDateTimeFilter"
            title="{{ __('Clear both date and time filters') }}"
        >
            {{ __('Clear Date & Time') }}
        </x-filament::button>
    @endif

    {{-- Max Events Capping Dropdown --}}
    <div
        wire:key="filter-dropdown-max"
        x-data="{ open: false }"
        style="position:relative;"
        x-on:keydown.escape.stop="open = false"
        x-on:pointerdown.outside="open = false"
    >
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-numbered-list"
            icon-position="before"
            x-on:pointerdown="open = !open"
        >
            {{ __('Max Events: ') . ($this->maxResults > 0 ? $this->maxResults : '∞') }}
        </x-filament::button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-75 motion-reduce:transition-none"
            x-transition:enter-start="opacity-0 -translate-y-1 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-50 motion-reduce:transition-none"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 -translate-y-1 scale-[0.98]"
            class="fi-dropdown-panel origin-top-left max-h-80 overflow-y-auto rounded-lg bg-white p-3 shadow-xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
            style="position:absolute;top:100%;left:0;margin-top:0.75rem;min-width:14rem;max-height:18rem;z-index:30;"
        >
            <div style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">
                {{ __('Limit Total Events') }}
            </div>
            <div style="display:flex;flex-direction:column;gap:0.375rem;">
                <button
                    type="button"
                    wire:click="setMaxResults(0)"
                    x-on:click="open = false"
                    style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                    class="border {{ empty($this->maxResults) ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                >
                    {{ __('∞ (All Events)') }}
                </button>
                @foreach ($this::MAX_RESULTS_OPTIONS as $limitOption)
                    <button
                        type="button"
                        wire:click="setMaxResults({{ $limitOption }})"
                        x-on:click="open = false"
                        style="display:flex;align-items:center;width:100%;text-align:left;padding:0.375rem 0.625rem;font-size:0.8125rem;font-weight:500;border-radius:0.375rem;cursor:pointer;"
                        class="border {{ $this->maxResults == $limitOption ? 'bg-primary-500 text-white border-primary-500' : 'border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                    >
                        {{ __(':count Events', ['count' => $limitOption]) }}
                    </button>
                @endforeach
            </div>

            {{-- Custom Limit Number Input --}}
            <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--gray-200);" class="dark:border-gray-800">
                <label style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;display:block;margin-bottom:0.375rem;">
                    {{ __('Custom Event Limit') }}
                </label>
                <div style="display:flex;gap:0.375rem;align-items:center;">
                    <input
                        type="number"
                        min="1"
                        step="1"
                        placeholder="e.g. 75"
                        wire:model.live.debounce.400ms="customMaxResults"
                        class="fi-input block w-full rounded-lg border-none bg-white py-1.5 px-2.5 text-xs text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                    @if ($this->maxResults > 0)
                        <button
                            type="button"
                            wire:click="setMaxResults(0)"
                            style="padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:0.375rem;border:1px solid var(--gray-300);background:var(--gray-100);cursor:pointer;white-space:nowrap;"
                            class="dark:border-gray-700 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
                            title="{{ __('Clear limit (set to all)') }}"
                        >
                            {{ __('Clear') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Reset All Filters Button --}}
    @if ($this->hasActiveFilters())
        <x-filament::button
            color="danger"
            size="sm"
            variant="link"
            icon="heroicon-m-x-circle"
            wire:click="resetFilters"
        >
            {{ __('Reset Filters') }}
        </x-filament::button>
    @endif

    {{-- Trailing Action Slot --}}
    {{ $slot ?? '' }}
</div>
