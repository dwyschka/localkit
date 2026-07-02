<?php

namespace App\Filament\Widgets;

use App\Models\BluetoothDevice;
use App\Models\Device;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DeviceStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total devices', Device::count())
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),
            Stat::make('Online', Device::where('mqtt_connected', 1)->count())
                ->descriptionIcon('heroicon-m-signal')
                ->color('success'),
            Stat::make('Needs attention', Device::whereNotNull('error')->where('error', '!=', '')->count())
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
            Stat::make('Linked BLE', BluetoothDevice::count())
                ->descriptionIcon('heroicon-m-link')
                ->color('gray'),
        ];
    }
}
