<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Filament\Widgets\DeviceStats;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDevices extends ListRecords
{
    protected static string $resource = DeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New device'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DeviceStats::class,
        ];
    }
}
