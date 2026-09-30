<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\InteractsWithProvisioning;
use Filament\Pages\Page;

/**
 * Hand a factory-fresh PetKit device its WiFi credentials and this server's
 * address over Bluetooth, so it never talks to PetKit's cloud at all.
 *
 * Everything this page does lives in InteractsWithProvisioning and in the
 * partial it renders; the same pair is behind the "Provision Device" action on
 * the device list.
 */
class ProvisioningPage extends Page
{
    use InteractsWithProvisioning;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-signal';

    protected string $view = 'filament.pages.provisioning-page';

    protected static ?string $slug = 'provisioning';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Provisioning';

    public function mount(): void
    {
        $this->mountProvisioningDefaults();
    }
}
