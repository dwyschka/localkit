<?php

namespace App\Petkit;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use App\Helpers\OTAHelper;
use App\Jobs\ServiceEnd;
use App\Jobs\ServiceStart;
use App\Localkit\OTA;
use App\Management\Go2RTC;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\Devices\PetkitYumshareDual;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
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
    public const RESET_DESICCANT = 'reset_desiccant';
    public const START_FEEDING = 'start_feeder';

    public const TAKE_SNAPSHOT = 'take_snapshot';

    public const LINK_WITH_K3 = 'link_with_k3';
    public const UNLINK_WITH_K3 = 'unlink_with_k3';

    public const REBOOT = 'reboot';

    public const RESET_WORKING_STATE = 'reset_working_state';

    public const RESET_ADD_WATER = 'reset_add_water';
    public const RESET_CUBE = 'reset_cube';
    public const DRAIN_AND_FLUSH = 'drain_and_flush';
    public const REFILL = 'refill';
    public const DRAIN = 'drain';
    public const DEEP_CLEAN = 'deep_clean';


    public static function actions()
    {
        return [
            Action::make('Check OTA')
                ->label('Check OTA')
//                ->visible(fn(Device $record) => $record->mqtt_connected)
                ->mountUsing(function (Schema $schema, Device $record) {
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

                    $schema->fill(['version' => $available['version']]);
                })
                ->schema([
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
            Action::make('Reset Desiccant')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_DESICCANT);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->resetDesiccant($record);
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
                ->schema(function (Device $record) {
                    $settings = $record->configuration['settings'] ?? [];

                    if ($record->definition() instanceof PetkitYumshareDual) {
                        return [
                            TextInput::make('amount1')
                                ->label('Hopper 1 Amount')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default($settings['amount1'] ?? 1),
                            TextInput::make('amount2')
                                ->label('Hopper 2 Amount')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->default($settings['amount2'] ?? 1),
                        ];
                    }

                    return [
                        TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->default($settings['amount'] ?? 10),
                    ];
                })
                ->modalHeading('Start Feeding')
                ->modalSubmitActionLabel('Feed')
                ->action(function (Device $record, array $data) {
                    $definition = $record->definition();

                    if ($definition instanceof PetkitYumshareDual) {
                        $definition->startFeeding($record, (int) $data['amount1'], (int) $data['amount2']);
                    } else {
                        $definition->startFeeding($record, (int) $data['amount']);
                    }
                }),
            Action::make('Take Snapshot')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::TAKE_SNAPSHOT);
                })
                ->action(function (Device $record) {
                    $record->definition()->takeSnapshot($record);
                }),
            Action::make('Watch Stream')
                ->visible(fn (Device $record) => $record->isNextGen() ?? false)
                ->modalHeading(fn (Device $record) => "Live Stream — {$record->name}")
                ->modalContent(fn (Device $record) => view('camera_stream', [
                    'streams' => app(Go2RTC::class)->streamUrls($record),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth('2xl'),
            Action::make('Reset Add Water')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_ADD_WATER);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->resetAddWater($record);
                }),
            Action::make('Reset Cube')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::RESET_CUBE);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->resetCube($record);
                }),
            Action::make('Drain and Flush')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::DRAIN_AND_FLUSH);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->drainAndFlush($record);
                }),
            Action::make('Refill')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::REFILL);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->refill($record);
                }),
            Action::make('Drain')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::DRAIN);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->drain($record);
                }),
            Action::make('Deep Clean')
                ->visible(function (Device $record) {
                    return $record->definition()->hasAction(self::DEEP_CLEAN);
                })
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->definition()->deepClean($record);
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
            // Available to every device: the `error` field is model-level (set e.g.
            // by a failed OTA, see DevOtaCompleteController), so clearing it does not need
            // per-device logic. Shown only while there is an error to clear.
            Action::make('Reset Error')
                ->label('Reset Error')
                ->visible(fn(Device $record) => filled($record->error))
                ->requiresConfirmation()
                ->action(function (Device $record) {
                    $record->update(['error' => null]);
                }),
        ];
    }
}
