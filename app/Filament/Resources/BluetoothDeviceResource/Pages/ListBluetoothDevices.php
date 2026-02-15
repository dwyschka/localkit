<?php

namespace App\Filament\Resources\BluetoothDeviceResource\Pages;

use App\Filament\Resources\BluetoothDeviceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBluetoothDevices extends ListRecords
{
    protected static string $resource = BluetoothDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
