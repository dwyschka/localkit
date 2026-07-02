<?php

namespace App\Filament\Widgets;

use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Models\Pet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OverviewStats extends BaseWidget
{
    protected static ?int $sort = -3;

    protected function getStats(): array
    {
        $devices = Device::count();
        $online  = Device::where('mqtt_connected', 1)->count();
        $errors  = Device::whereNotNull('error')->where('error', '!=', '')->count();
        $pets    = Pet::count();
        $ble     = BluetoothDevice::count();

        return [
            Stat::make('Devices', $devices)
                ->description($ble . ' linked BLE accessories')
                ->descriptionIcon('heroicon-m-rectangle-stack')
                ->color('primary'),

            Stat::make('Online', $online)
                ->description('Connected over MQTT')
                ->descriptionIcon('heroicon-m-signal')
                ->color('success'),

            Stat::make('Needs attention', $errors)
                ->description($errors > 0 ? 'Devices reporting an error' : 'All devices healthy')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($errors > 0 ? 'danger' : 'gray'),

            Stat::make('Pets', $pets)
                ->description('Registered pets')
                ->descriptionIcon('heroicon-m-heart')
                ->color('gray'),
        ];
    }
}
