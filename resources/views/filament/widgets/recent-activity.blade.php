<x-filament-widgets::widget>
    <x-filament::section heading="Last Activity">
        <x-slot name="afterHeader">
            <x-filament::button
                color="gray"
                tag="a"
                href="{{ \App\Filament\Pages\ActivitiesPage::getUrl() }}"
                size="xs"
                icon="heroicon-m-arrow-right"
                icon-position="after"
            >
                {{ __('View all') }}
            </x-filament::button>
        </x-slot>

        <x-petkit-timeline
            :histories="$histories"
            :compact="true"
        />
    </x-filament::section>
</x-filament-widgets::widget>
