<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Set a device up over Bluetooth</x-slot>
        <x-slot name="description">
            Hands a device its WiFi credentials and this server's address directly, so it never
            contacts PetKit's cloud. No PetKit app and no DNS redirect involved.
        </x-slot>

        @include('filament.partials.provisioning', [
            ...$this->provisionWifiDefaults(),
            'server' => $this->provisionServer,
            'timezone' => $this->provisionTimezone,
            'zone' => $this->provisionZone,
            'timezoneOptions' => $this->provisionTimezoneOptions(),
            'installSteps' => $this->provisioningInstallSteps(),
        ])
    </x-filament::section>
</x-filament-panels::page>
