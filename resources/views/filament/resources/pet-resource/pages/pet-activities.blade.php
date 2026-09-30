<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $this->record->name }}</x-slot>

        <x-petkit-timeline
            :histories="$this->getHistories()"
            :show-pet-tag="false"
        />
    </x-filament::section>
</x-filament-panels::page>
