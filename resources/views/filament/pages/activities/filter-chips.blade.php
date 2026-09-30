<div {{ $attributes->merge(['style' => 'display:inline-flex;align-items:center;gap:0.375rem;flex-wrap:wrap;']) }}>
    {{-- Device Chips --}}
    @foreach ($this->activeDeviceFilters() as $id => $name)
        <button
            type="button"
            wire:click="removeDeviceFilter('{{ $id }}')"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--primary-500) 15%, transparent);color:var(--primary-600);border:none;cursor:pointer;"
            title="{{ __('Remove filter') }}"
        >
            <x-filament::icon icon="heroicon-m-rectangle-stack" style="width:0.75rem;height:0.75rem;" />
            <span>{{ $name }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endforeach

    {{-- Pet Chips --}}
    @foreach ($this->activePetFilters() as $id => $name)
        <button
            type="button"
            wire:click="removePetFilter('{{ $id }}')"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--pink-500, #ec4899) 15%, transparent);color:var(--pink-600, #db2777);border:none;cursor:pointer;"
            title="{{ __('Remove filter') }}"
        >
            <x-filament::icon icon="heroicon-m-heart" style="width:0.75rem;height:0.75rem;" />
            <span>{{ $name }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endforeach

    {{-- Type Chips --}}
    @foreach ($this->activeTypeFilters() as $key => $label)
        <button
            type="button"
            wire:click="removeTypeFilter('{{ $key }}')"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--purple-500, #a855f7) 15%, transparent);color:var(--purple-600, #9333ea);border:none;cursor:pointer;"
            title="{{ __('Remove filter') }}"
        >
            <x-filament::icon icon="heroicon-m-tag" style="width:0.75rem;height:0.75rem;" />
            <span>{{ $label }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endforeach

    {{-- Media Type Chips --}}
    @foreach ($this->activeMediaFilters() as $type => $label)
        <button
            type="button"
            wire:click="removeMediaFilter('{{ $type }}')"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, #0ea5e9 15%, transparent);color:#0284c7;border:none;cursor:pointer;"
            title="{{ __('Remove media filter') }}"
        >
            <x-filament::icon icon="heroicon-m-photo" style="width:0.75rem;height:0.75rem;" />
            <span>{{ $label }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endforeach

    {{-- Date Filter Chip --}}
    @if ($this->activeDateFilterLabel())
        <button
            type="button"
            wire:click="removeDateFilter"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--amber-500, #f59e0b) 15%, transparent);color:var(--amber-600, #d97706);border:none;cursor:pointer;"
            title="{{ __('Remove date filter') }}"
        >
            <x-filament::icon icon="heroicon-m-calendar" style="width:0.75rem;height:0.75rem;" />
            <span>{{ $this->activeDateFilterLabel() }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endif

    {{-- Time Filter Chip --}}
    @if ($this->activeTimeFilterLabel())
        <button
            type="button"
            wire:click="removeTimeFilter"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--cyan-500, #06b6d4) 15%, transparent);color:var(--cyan-600, #0891b2);border:none;cursor:pointer;"
            title="{{ __('Remove time filter') }}"
        >
            <x-filament::icon icon="heroicon-m-clock" style="width:0.75rem;height:0.75rem;" />
            <span
                x-data="{ tf: '{{ $this->timeFrom }}', tt: '{{ $this->timeTo }}' }"
                x-text="(() => {
                    const fmt = (t) => {
                        if (!t) return '';
                        const [h, m] = t.split(':').map(Number);
                        const d = new Date(); d.setHours(h, m, 0, 0);
                        return d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                    };
                    if (tf && tt) {
                        if (tf === '05:00' && tt === '08:00') return '{{ __('Early Morning') }} (' + fmt(tf) + ' – ' + fmt(tt) + ')';
                        if (tf === '08:00' && tt === '12:00') return '{{ __('Morning') }} (' + fmt(tf) + ' – ' + fmt(tt) + ')';
                        if (tf === '12:00' && tt === '17:00') return '{{ __('Afternoon') }} (' + fmt(tf) + ' – ' + fmt(tt) + ')';
                        if (tf === '17:00' && tt === '21:00') return '{{ __('Evening') }} (' + fmt(tf) + ' – ' + fmt(tt) + ')';
                        if (tf === '21:00' && tt === '06:00') return '{{ __('Night') }} (' + fmt(tf) + ' – ' + fmt(tt) + ')';
                        return fmt(tf) + ' – ' + fmt(tt);
                    }
                    if (tf) return '{{ __('From ') }}' + fmt(tf);
                    if (tt) return '{{ __('Until ') }}' + fmt(tt);
                    return '{{ $this->activeTimeFilterLabel() }}';
                })()"
            >{{ $this->activeTimeFilterLabel() }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endif

    {{-- Combined Date & Time Clear Chip --}}
    @if ($this->activeDateFilterLabel() && $this->activeTimeFilterLabel())
        <button
            type="button"
            wire:click="removeDateTimeFilter"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--rose-500, #f43f5e) 15%, transparent);color:var(--rose-600, #e11d48);border:none;cursor:pointer;"
            title="{{ __('Clear both date and time filters') }}"
        >
            <x-filament::icon icon="heroicon-m-calendar-days" style="width:0.75rem;height:0.75rem;" />
            <span>{{ __('Clear Date & Time') }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endif

    {{-- Max Events Limit Chip --}}
    @if ($this->maxResults !== $this::DEFAULT_MAX_RESULTS)
        <button
            type="button"
            wire:click="removeMaxResultsFilter"
            style="display:inline-flex;align-items:center;gap:0.25rem;padding:0.15rem 0.5rem;font-size:0.75rem;font-weight:500;border-radius:9999px;background:color-mix(in oklch, var(--emerald-500, #10b981) 15%, transparent);color:var(--emerald-600, #059669);border:none;cursor:pointer;"
            title="{{ __('Reset max events limit to default (:count)', ['count' => $this::DEFAULT_MAX_RESULTS]) }}"
        >
            <x-filament::icon icon="heroicon-m-numbered-list" style="width:0.75rem;height:0.75rem;" />
            <span>{{ __('Max Events: ') . ($this->maxResults > 0 ? $this->maxResults : '∞ (All)') }}</span>
            <x-filament::icon icon="heroicon-m-x-mark" style="width:0.75rem;height:0.75rem;opacity:0.7;" />
        </button>
    @endif
</div>
