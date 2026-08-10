<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\IconColumn;
use App\Management\Go2RTC;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;


class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->columnSpan('full'),
                TextInput::make('firmware')->columnSpan('half')->readOnly(),
                TextInput::make('serial_number')->columnSpan('half')->readOnly(),
                TextInput::make('mac')->columnSpan('half')->readOnly(),
                Select::make('device_type')->options([
                    't4' => PetkitPuraMax::deviceName(),
                    'd3' => PetkitFreshElement3::deviceName(),
                    'd4' => PetkitFreshElementSolo::deviceName(),
                    'd4h' => PetkitYumshareSolo::deviceName(),
                    'd4sh' => PetkitYumshareDual::deviceName(),
                    't7' => PetkitPurobotCrystal::deviceName(),
                    'w7h' => PetkitEversweetUltra::deviceName(),
                ])
                    ->columnSpan('half')->disabled(),

                TextInput::make('secret')->columnSpan('half'),
                TextInput::make('petkit_id')->columnSpan('half')->readOnly(),
                TextInput::make('mqtt_subdomain')->columnSpan('half'),
                Toggle::make('ota_state')
                    ->helperText('If enabled, the MQTT Connection to Aliyun needs to be disabled')
                    ->columnSpan('half'),
                Toggle::make('ota_available')
                    ->helperText('Set by the device firmware — indicates whether an OTA update is available')
                    ->columnSpan('half')
                    ->disabled(),
                Toggle::make('debug_mode')
                    ->columnSpan('half')
                    ->helperText('Logs all incoming HTTP requests from this device to storage/logs/device_{serial}.log'),

                Fieldset::make('Device Configuration')
                    ->columnSpanFull()
                    ->columns(1)
                    ->schema([
                        ...$schema->getModelInstance()->ui()->formFields(),
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
                        TextColumn::make('device_type')
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
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
                        TextColumn::make('working_state')
                            ->badge()
                            ->grow(false),
                        IconColumn::make('ota_available')
                            ->label('OTA')
                            ->icon(fn(bool $state): ?string => $state ? 'heroicon-m-arrow-down-tray' : null)
                            ->color('warning')
                            ->grow(false),
                    ]),

                    TextColumn::make('name')
                        ->searchable()
                        ->color('gray')
                        ->icon('heroicon-m-tag'),

                    Split::make([
                        TextColumn::make('mqtt_connected')
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
                        TextColumn::make('bleLinked.name')
                            ->badge()
                            ->grow(false)
                            ->icon('heroicon-m-link')
                            ->color('info'),
                    ]),

                    TextColumn::make('error')
                        ->badge()
                        ->icon('heroicon-m-exclamation-triangle')
                        ->formatStateUsing(function (string $state) {
                            return __('petkit.error.' . $state);
                        })
                        ->color(fn(string $state): string => 'danger'),

                    ViewColumn::make('camera_stream_tile')
                        ->view('tables.columns.camera-stream-tile')
                        ->viewData(fn (Device $record) => [
                            'streams' => ($record->isNextGen() ?? false)
                                ? app(Go2RTC::class)->streamUrls($record)
                                : [],
                        ]),
                ])->space(3),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->color('gray')
                    ->size('sm'),
                Action::make('view_activities')
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
            ->recordActionsAlignment('start');
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
            'index' => ListDevices::route('/'),
            'edit' => EditDevice::route('/{record}/edit'),
            'activities' => Pages\PetkitActivities::route('/{record}/activities'),
        ];
    }
}
