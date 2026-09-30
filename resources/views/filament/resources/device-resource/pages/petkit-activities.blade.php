<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ $this->record->name ?? $this->record->serial_number }}</x-slot>

        <x-petkit-timeline
            :histories="$this->getHistories()"
            :show-device-tag="false"
        />
    </x-filament::section>
</x-filament-panels::page>
