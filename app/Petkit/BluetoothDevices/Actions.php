<?php

namespace App\Petkit\BluetoothDevices;

use Filament\Actions\Action;
use App\Models\BluetoothDevice;
use App\Models\Device;
use Filament\Forms\Components\Select;

class Actions
{
    public const REFRESH = 'refresh_device_data';
    public const RESET_FILTER = 'reset_filter';

    public static function actions()
    {
        return [
            Action::make('Refresh Device Data')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::REFRESH) && $record->linkWith()->exists();
                })
                ->action(fn (BluetoothDevice $record) => $record->device()->refresh()),
            Action::make('Reset Filter')
                ->visible(function (BluetoothDevice $record) {
                    return $record->device()->hasAction(self::RESET_FILTER) && $record->linkWith()->exists();
                })
                ->requiresConfirmation()
                ->action(fn (BluetoothDevice $record) => $record->device()->resetFilter()),
        ];
    }


}
