<?php

namespace App\Petkit\BluetoothDevices;

use App\Models\BluetoothDevice;
use App\Models\Device;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\Action;

class Actions
{
    public const REFRESH = 'refresh_device_data';

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
                })
        ];
    }


}
