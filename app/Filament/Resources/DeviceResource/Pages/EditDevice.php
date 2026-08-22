<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetConfig')
                ->label('Reset Config')
                ->color('danger')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Reset configuration to defaults?')
                ->modalDescription('This overwrites the stored configuration for this device with its type\'s defaults - schedule, settings, everything - and pushes the reset to the device. This cannot be undone.')
                ->action(function (Device $record) {
                    $record->update([
                        'configuration' => $record->definition()->resetConfiguration(),
                    ]);

                    Notification::make()
                        ->title('Configuration reset to defaults')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            DeleteAction::make(),
        ];
    }
}
