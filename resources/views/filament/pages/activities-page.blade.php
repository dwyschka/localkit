<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <x-filament::icon icon="heroicon-o-bolt" style="width:1.5rem;height:1.5rem;color:var(--primary-500);" />
                <span>{{ __('Activities Timeline') }}</span>
                <span class="petkit-player-btn-badge" style="background:var(--primary-500);color:#ffffff;font-size:0.75rem;padding:0.125rem 0.5rem;border-radius:9999px;">
                    {{ $this->getFilteredActivitiesCount() }} {{ __('events') }}
                </span>
            </div>
        </x-slot>

        {{-- Shared Activity Filter Toolbar --}}
        @include('filament.pages.activities.filter-toolbar')

        {{-- Active Filter Chips --}}
        @if ($this->hasActiveFilters())
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0.5rem;margin-bottom:1.5rem;padding-top:0.25rem;">
                <span style="font-size:0.75rem;font-weight:600;color:var(--gray-400);text-transform:uppercase;letter-spacing:0.04em;">
                    {{ __('Active filters:') }}
                </span>
                @include('filament.pages.activities.filter-chips')
            </div>
        @endif

        <x-petkit-timeline
            :histories="$this->getHistories()"
            :empty-message="$this->hasActiveFilters() ? __('No activities match the selected filters.') : __('No activities recorded yet.')"
        />
    </x-filament::section>
</x-filament-panels::page>
