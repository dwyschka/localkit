<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Jobs\ServiceStart;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\DeviceActions;
use App\Petkit\Devices\PetkitFreshElement3;
use App\Petkit\Devices\PetkitFreshElementSolo;
use App\Petkit\Devices\PetkitPuraMax;
use App\Petkit\Devices\PetkitPurobotCrystal;
use App\Petkit\Devices\PetkitYumshareSolo;
use App\Petkit\Devices\PetkitYumshareDual;
use App\Petkit\Devices\PetkitEversweetUltra;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->columnSpan('full'),
                Forms\Components\TextInput::make('firmware')->columnSpan('half')->readOnly(),
                Forms\Components\TextInput::make('mac')->columnSpan('half')->readOnly(),
                Forms\Components\Select::make('device_type')->options([
                    't4' => PetkitPuraMax::deviceName(),
                    'd3' => PetkitFreshElement3::deviceName(),
                    'd4' => PetkitFreshElementSolo::deviceName(),
                    'd4h' => PetkitYumshareSolo::deviceName(),
                    'd4sh' => PetkitYumshareDual::deviceName(),
                    't7' => PetkitPurobotCrystal::deviceName(),
                    'w7h' => PetkitEversweetUltra::deviceName(),
                ])
                    ->columnSpan('half')->disabled(),

                Forms\Components\TextInput::make('secret')->columnSpan('half'),
                Forms\Components\TextInput::make('petkit_id')->columnSpan('half')->readOnly(),
                Forms\Components\TextInput::make('mqtt_subdomain')->columnSpan('half'),
                Forms\Components\Toggle::make('ota_state')
                    ->helperText('If enabled, the MQTT Connection to Aliyun needs to be disabled')
                    ->columnSpan('half'),
                Forms\Components\Toggle::make('ota_available')
                    ->helperText('Set by the device firmware — indicates whether an OTA update is available')
                    ->columnSpan('half')
                    ->disabled(),
                Forms\Components\Toggle::make('debug_mode')
                    ->columnSpan('half')
                    ->helperText('Logs all incoming HTTP requests from this device to storage/logs/device_{serial}.log'),

                Forms\Components\Fieldset::make('Device Configuration')->schema([
                    ...$form->getModelInstance()->ui()->formFields(),
                ])

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Stack::make([
                    Split::make([
                        Tables\Columns\TextColumn::make('device_type')
                            ->weight(FontWeight::Bold)
                            ->size(Tables\Columns\TextColumn\TextColumnSize::Large)
                            ->formatStateUsing(function (string $state) {
                                return match ($state) {
                                    't4' => PetkitPuraMax::deviceName(),
                                    'd3' => PetkitFreshElement3::deviceName(),
                                    'd4' => PetkitFreshElementSolo::deviceName(),
                                    'd4h' => PetkitYumshareSolo::deviceName(),
                                    'd4sh' => PetkitYumshareDual::deviceName(),
                                    't7' => PetkitPurobotCrystal::deviceName(),
                                    'w7h' => PetkitEversweetUltra::deviceName(),
                                };
                            }),
                        Tables\Columns\TextColumn::make('working_state')
                            ->badge()
                            ->grow(false),
                    ]),

                    Tables\Columns\TextColumn::make('name')
                        ->searchable()
                        ->color('gray')
                        ->icon('heroicon-m-tag'),

                    Split::make([
                        Tables\Columns\TextColumn::make('mqtt_connected')
                            ->badge()
                            ->grow(false)
                            ->icon(fn(string $state): string => $state === '0' ? 'heroicon-m-signal-slash' : 'heroicon-m-signal')
                            ->formatStateUsing(function (string $state) {
                                return $state === '0' ? 'Disconnected' : 'Connected';
                            })
                            ->color(fn(string $state): string => match ($state) {
                                '0' => 'danger',
                                '1' => 'success'
                            }),
                        Tables\Columns\TextColumn::make('bleLinked.name')
                            ->badge()
                            ->grow(false)
                            ->icon('heroicon-m-link')
                            ->color('info'),
                    ]),

                    Tables\Columns\TextColumn::make('error')
                        ->badge()
                        ->icon('heroicon-m-exclamation-triangle')
                        ->formatStateUsing(function (string $state) {
                            return __('petkit.error.' . $state);
                        })
                        ->color(fn(string $state): string => 'danger'),

                    Split::make([
                        Tables\Columns\TextColumn::make('serial_number')
                            ->color('gray')
                            ->icon('heroicon-m-hashtag')
                            ->copyable(),
                        Tables\Columns\IconColumn::make('ota_available')
                            ->label('OTA')
                            ->boolean()
                            ->grow(false),
                    ]),
                ])->space(3),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->button()
                    ->color('gray')
                    ->size('sm'),
                Tables\Actions\Action::make('view_activities')
                    ->label('Activities')
                    ->icon('heroicon-m-bolt')
                    ->color('purple')
                    ->button()
                    ->size('sm')
                    ->url(fn($record) => DeviceResource::getUrl('activities', ['record' => $record])),

                ...array_map(
                    function (Action $action) {
                        $name = $action->getName();

                        // De-emphasise maintenance/reset actions so the card isn't a wall of primary buttons.
                        $isMaintenance = preg_match('/^(Reset|Drain|Reboot|Deep Clean|Stop)/i', $name) === 1;

                        return $action
                            ->button()
                            ->size('sm')
                            ->icon(self::actionIcon($name))
                            ->color($isMaintenance ? 'gray' : 'primary');
                    },
                    DeviceActions::actions()
                ),
            ])
            ->actionsAlignment('left');
    }

    /**
     * Pick a sensible heroicon for a device control action based on its name.
     */
    protected static function actionIcon(string $name): string
    {
        return match (true) {
            str_contains($name, 'OTA') => 'heroicon-m-arrow-down-tray',
            str_starts_with($name, 'Start Cleaning') => 'heroicon-m-sparkles',
            str_contains($name, 'Feeding') => 'heroicon-m-cake',
            str_contains($name, 'Snapshot') => 'heroicon-m-camera',
            str_contains($name, 'Deodorize'), str_contains($name, 'Odour') => 'heroicon-m-beaker',
            str_contains($name, 'Lightning') => 'heroicon-m-bolt',
            str_contains($name, 'Level') => 'heroicon-m-scale',
            str_contains($name, 'Litter') => 'heroicon-m-trash',
            str_contains($name, 'Maintenance') => 'heroicon-m-wrench-screwdriver',
            str_contains($name, 'Drain'), str_contains($name, 'Refill') => 'heroicon-m-beaker',
            str_contains($name, 'Reboot') => 'heroicon-m-power',
            str_starts_with($name, 'Reset') => 'heroicon-m-arrow-path',
            default => 'heroicon-m-play',
        };
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
            'activities' => Pages\PetkitActivities::route('/order/{record}/activities'),

        ];
    }
}
