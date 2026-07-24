<?php

namespace App\Petkit;

use App\Helpers\OTAHelper;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Localkit\OTA;
use App\Models\BluetoothDevice;
use App\Models\Device;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class DeviceActions
{
    public const START_CLEAN = 'start_clean';
    public const DEODORIZE = 'deodorize';
    public const LEVEL = 'level';
    public const START_MAINTENANCE = 'start_maintenance';
    public const STOP_MAINTENANCE = 'stop_maintenance';
    public const CLEAN_LITTER = 'clean_litter';
    public const START_ODOUR = 'start_odour';
    public const START_LIGHTNING = 'start_lightning';
    public const STOP_LIGHTNING = 'stop_lightning';
    public const RESET_N50 = 'reset_n50';
    public const RESET_N60 = 'reset_n60';
    public const RESET_CARDBOARD = 'reset_cardboard';
    public const START_FEEDING = 'start_feeder';

    public const TAKE_SNAPSHOT = 'take_snapshot';

    public const LINK_WITH_K3 = 'link_with_k3';
    public const UNLINK_WITH_K3 = 'unlink_with_k3';

    public const REBOOT = 'reboot';

    public const RESET_WORKING_STATE = 'reset_working_state';


    public static function actions()
    {
        return [
            Action::make('Check OTA')
                ->label('Check OTA')
//                ->visible(fn(Device $record) => $record->mqtt_connected)
                ->mountUsing(function (Form $form, Device $record) {
                    $available = app(OTA::class)->getAvailable($record);

                    if (!$available) {
                        Notification::make()
                            ->danger()
                            ->title('No OTA available')
                            ->send();
                        throw new Halt();
                    }

                    $record->update([
                        'ota_available' => 1,
                        'available_version' => $available['version'],
                    ]);

                    $form->fill(['version' => $available['version']]);
                })
                ->form([
                    Placeholder::make('version_display')
                        ->label('Update Available')
                        ->content(fn(Get $get): string => $get('version') ?? ''),
                    Hidden::make('version'),
                ])
                ->modalHeading('Update Available')
                ->modalDescription('Do you want to install this update?')
                ->modalSubmitActionLabel('Install')
                ->action(function (Device $record, array $data) {

                    $record->update([
                        'ota_state' => 1,
                    ]);
                }),
            Action::make('Start Cleaning')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_CLEAN);
                })
                ->action(function (Device $record) {
                    $record->definition()->startCleaning($record);
                }),
            Action::make('Deodorize')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::DEODORIZE);
                })
                ->action(function (Device $record) {
                    $record->definition()->deodorize($record);
                }),
            Action::make('Level')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::LEVEL);
                })
                ->action(function (Device $record) {
                    $record->definition()->level($record);
                }),
            Action::make('Start Maintenance')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_MAINTENANCE);
                })
                ->action(function (Device $record) {
                    $record->definition()->startMaintenance($record);
                }),
            Action::make('Stop Maintenance')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::STOP_MAINTENANCE);
                })
                ->action(function (Device $record) {
                    $record->definition()->stopMaintenance($record);
                }),
            Action::make('Dump Litter')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::CLEAN_LITTER);
                })
                ->action(function (Device $record) {
                    $record->definition()->cleanLitter($record);
                }),
            Action::make('Reset N50')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_N50);
                })
                ->action(function (Device $record) {
                    $record->definition()->resetN50($record);
                }),
            Action::make('Reset N60')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_N60);
                })
                ->action(function (Device $record) {
                    $record->definition()->resetN60($record);
                }),
            Action::make('Reset Cardboard')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_CARDBOARD);
                })
                ->action(function (Device $record) {
                    $record->definition()->resetCardboard($record);
                }),
            Action::make('Start Odour')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_ODOUR);
                })
                ->action(function (Device $record) {
                    $record->definition()->startOdour($record);
                }),
            Action::make('Start Lightning')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_LIGHTNING);
                })
                ->action(function (Device $record) {
                    $record->definition()->startLightning($record);
                }),
            Action::make('Stop Lightning')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::STOP_LIGHTNING);
                })
                ->action(function (Device $record) {
                    $record->definition()->stopLightning($record);
                }),
            Action::make('Start Feeding')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_FEEDING);
                })
                ->action(function (Device $record) {
                    $record->definition()->startFeeding($record);
                }),
            Action::make('Take Snapshot')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::TAKE_SNAPSHOT);
                })
                ->action(function (Device $record) {
                    $record->definition()->takeSnapshot($record);
                }),
            Action::make('Reboot')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::REBOOT);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->reboot($record);
                }),
            Action::make('Reset State')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_WORKING_STATE);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->resetWorkingState($record);
                }),
        ];
    }
}
