<?php

namespace App\Petkit\BluetoothDevices;

use Filament\Actions\Action;
use App\Models\BluetoothDevice;
use App\Models\Device;
use Filament\Forms\Components\Select;

class Actions
{
    public const REFRESH = 'refresh_device_data';
    public const MODE_NORMAL = 'mode_normal';
    public const MODE_SMART = 'mode_smart';
    public const POWER_OFF = 'power_off';
    public const RESET_FILTER = 'reset_filter';

    public static function actions()
    {
        return [
            Action::make('Refresh Device Data')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::REFRESH) && $record->linkWith()->exists();
                })
                ->action(function ( BluetoothDevice $record) {
                    $proxyDevice = $record->linkWith;

                    $definition = $proxyDevice->definition();

                    if ($definition instanceof BluetoothProxyInterface) {
                        $definition->btConnect($record);
                    }
                }),
            Action::make('Turn On (Normal)')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::MODE_NORMAL) && $record->linkWith()->exists();
                })
                ->action(fn (BluetoothDevice $record) => $record->device()->setMode(1, 1)),
            Action::make('Turn On (Smart)')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::MODE_SMART) && $record->linkWith()->exists();
                })
                ->action(fn (BluetoothDevice $record) => $record->device()->setMode(1, 2)),
            Action::make('Turn Off')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::POWER_OFF) && $record->linkWith()->exists();
                })
                ->requiresConfirmation()
                ->action(fn (BluetoothDevice $record) => $record->device()->setMode(0, $record->configuration()->mode ?: 1)),
            Action::make('Reset Filter')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::RESET_FILTER) && $record->linkWith()->exists();
                })
                ->requiresConfirmation()
                ->action(fn (BluetoothDevice $record) => $record->device()->resetFilter()),
        ];
    }


}
