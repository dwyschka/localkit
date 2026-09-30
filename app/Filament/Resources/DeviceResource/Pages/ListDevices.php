<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Concerns\InteractsWithProvisioning;
use App\Filament\Resources\DeviceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListDevices extends ListRecords
{
    use InteractsWithProvisioning;

    protected static string $resource = DeviceResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->mountProvisioningDefaults();
    }

    protected function getHeaderActions(): array
    {
        return [
            /*
             * Adding a device means provisioning one, so the button belongs
             * here rather than only on its own page. The form is the same
             * partial that page renders; the Bluetooth work happens in the
             * browser, so there is nothing to submit and no server action
             * behind this modal.
             */
            Action::make('provision')
                ->label('Provision Device')
                ->icon('heroicon-o-signal')
                ->color('primary')
                ->modalHeading('Set a device up over Bluetooth')
                ->modalDescription(
                    "Hands a device its WiFi credentials and this server's address directly, so it "
                    . "never contacts PetKit's cloud. No PetKit app and no DNS redirect involved."
                )
                ->modalWidth(Width::ThreeExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (): \Illuminate\Contracts\View\View => view(
                    'filament.partials.provisioning',
                    [
                        ...$this->provisionWifiDefaults(),
                        'server' => $this->provisionServer,
                        'timezone' => $this->provisionTimezone,
                        'zone' => $this->provisionZone,
                        'timezoneOptions' => $this->provisionTimezoneOptions(),
                        'installSteps' => $this->provisioningInstallSteps(),
                    ],
                )),
        ];
    }
}
