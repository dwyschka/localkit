<?php

namespace App\Petkit;

use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Models\Device;
use Filament\Actions\ActionGroup;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class DeviceActions
{
    public const START_CLEAN = 'start_clean';
    public const START_MAINTENANCE = 'start_maintenance';
    public const STOP_MAINTENANCE = 'stop_maintenance';
    public const CLEAN_LITTER = 'clean_litter';
    public const START_ODOUR = 'start_odour';
    public const START_LIGHTNING = 'start_lightning';

    public static function actions()
    {
        return [
            Action::make('Start Cleaning')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::START_CLEAN);
                })
                ->action(function (Device $record) {
                    $record->definition()->startCleaning($record);
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
            Action::make('Clean Litter')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::CLEAN_LITTER);
                })
                ->action(function (Device $record) {
                    $record->definition()->cleanLitter($record);
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
                })
        ];
    }
}
