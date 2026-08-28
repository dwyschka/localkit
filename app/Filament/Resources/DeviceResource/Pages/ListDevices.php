<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('install')
                ->label('Install Device')
                ->icon('heroicon-m-wrench-screwdriver')
                ->modalWidth('5xl')
                ->modalContent(fn() => view('filament.actions.install-modal', [
                    'defaultIp' => null,
                ]))
                ->modalDescription('Installs LocalKit on the device over telnet using its built-in root credentials.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }
}
