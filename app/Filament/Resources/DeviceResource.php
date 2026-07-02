<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers;
use App\Jobs\ServiceStart;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\DeviceActions;
use App\Petkit\Devices\PetkitFreshElementSolo;
use App\Petkit\Devices\PetkitPuraMax;
use App\Petkit\Devices\PetkitYumshareSolo;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
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
        $model = $form->getModelInstance();

        $general = Forms\Components\Tabs\Tab::make('General')
            ->icon('heroicon-o-cog-6-tooth')
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('name')->columnSpanFull(),
                Forms\Components\Select::make('device_type')->options([
                    't4' => PetkitPuraMax::deviceName(),
                    'd4' => PetkitFreshElementSolo::deviceName(),
                    'd4h' => PetkitYumshareSolo::deviceName(),
                ])->disabled(),
                Forms\Components\TextInput::make('serial_number')->readOnly(),
                Forms\Components\TextInput::make('firmware')->readOnly(),
                Forms\Components\TextInput::make('mac')->readOnly(),
                Forms\Components\TextInput::make('petkit_id')->readOnly(),
                Forms\Components\TextInput::make('secret'),
                Forms\Components\TextInput::make('mqtt_subdomain'),
                Forms\Components\Fieldset::make('Connection & proxy')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Toggle::make('proxy_mode')
                            ->helperText('If the field is disabled, please set a secret and the MQTT subdomain')
                            ->disabled(fn ($record) => empty($record?->secret) || empty($record?->mqtt_subdomain)),
                        Forms\Components\Toggle::make('debug_mode')
                            ->helperText('Logs all incoming HTTP requests from this device to storage/logs'),
                        Forms\Components\Toggle::make('ota_state')
                            ->helperText('If enabled, the MQTT connection to Aliyun needs to be disabled'),
                        Forms\Components\Toggle::make('ota_available')
                            ->helperText('Set by the device firmware — indicates whether an OTA update is available')
                            ->disabled(),
                    ]),
            ]);

        $config = ($model->exists && $model->device_type) ? $model->ui()->formFields() : [];
        $configTabs = self::normalizeConfigTabs($config);

        return $form->schema([
            Forms\Components\Placeholder::make('summary')
                ->hiddenLabel()
                ->columnSpanFull()
                ->content(fn (?\App\Models\Device $record) => $record?->exists
                    ? view('filament.device-summary', ['device' => $record])
                    : ''),
            Forms\Components\Tabs::make('Device')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs(array_merge([$general], $configTabs)),
        ]);
    }

    public static function normalizeConfigTabs(array $config): array
    {
        $icons = [
            'Consumables' => 'heroicon-o-beaker',
            'Feeding' => 'heroicon-o-cake',
            'Feeding Plan' => 'heroicon-o-calendar-days',
            'Settings' => 'heroicon-o-cog-6-tooth',
            'Smart Settings' => 'heroicon-o-sparkles',
            'Media' => 'heroicon-o-video-camera',
            'Camera Settings' => 'heroicon-o-camera',
            'Voice Settings' => 'heroicon-o-speaker-wave',
            'AI LAB' => 'heroicon-o-sparkles',
            'Litter' => 'heroicon-o-square-3-stack-3d',
            'Unknown' => 'heroicon-o-wrench-screwdriver',
            'Advanced' => 'heroicon-o-wrench-screwdriver',
        ];

        $tabs = [];
        foreach ($config as $component) {
            if ($component instanceof Forms\Components\Tabs\Tab) {
                $tabs[] = $component;
            } elseif ($component instanceof Forms\Components\Section) {
                $heading = $component->getHeading();
                $label = (is_string($heading) && $heading !== '') ? $heading : 'Configuration';
                $tabs[] = Forms\Components\Tabs\Tab::make($label)
                    ->icon($icons[$label] ?? 'heroicon-o-adjustments-horizontal')
                    ->schema($component->getChildComponents())
                    ->columns($component->getColumns() ?: 1);
            } else {
                $tabs[] = Forms\Components\Tabs\Tab::make('Configuration')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([$component]);
            }
        }

        return $tabs;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s')
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Device')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->icon(fn ($record): string => $record->device_type === 't4' ? 'heroicon-o-archive-box' : 'heroicon-o-inbox-stack')
                    ->iconColor('primary')
                    ->extraCellAttributes(['class' => 'lk-cardhead']),
                Tables\Columns\TextColumn::make('device_type')
                    ->label('Type')
                    ->badge()->color('gray')->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        't4' => PetkitPuraMax::deviceName(),
                        'd4' => PetkitFreshElementSolo::deviceName(),
                        'd4h' => PetkitYumshareSolo::deviceName(),
                        default => $state,
                    })
                    ->extraCellAttributes(['data-label' => 'Type']),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('Serial')
                    ->searchable()->sortable()
                    ->color('gray')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->extraCellAttributes(['data-label' => 'Serial']),
                Tables\Columns\TextColumn::make('mqtt_connected')
                    ->label('Connection')
                    ->sortable()
                    ->html()
                    ->formatStateUsing(function (string $state): string {
                        $connected = $state !== '0';
                        $color = $connected ? '#4ade80' : '#f87171';
                        $text = $connected ? 'Connected' : 'Disconnected';
                        return '<span style="display:inline-flex;align-items:center;gap:7px;font-weight:600;color:' . $color . '">'
                            . '<span style="width:8px;height:8px;border-radius:50%;flex:none;background:' . $color . '"></span>'
                            . $text . '</span>';
                    })
                    ->extraCellAttributes(['data-label' => 'Connection']),
                Tables\Columns\TextColumn::make('working_state')
                    ->label('State')
                    ->badge()->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        if (! $record->error) {
                            return $state;
                        }
                        $key = 'petkit.error.' . $record->error;
                        $message = __($key);

                        return $message === $key ? 'Error: ' . $record->error : $message;
                    })
                    ->color(fn ($state, $record): string => $record->error ? 'danger' : 'warning')
                    ->wrap()
                    ->extraCellAttributes(['data-label' => 'State']),
                Tables\Columns\TextColumn::make('last_heartbeat')
                    ->label('Last heartbeat')
                    ->sortable()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::createFromTimestamp((int) $state)->diffForHumans() : '—')
                    ->description(fn ($state) => $state ? \Illuminate\Support\Carbon::createFromTimestamp((int) $state)->format('Y-m-d H:i') : null)
                    ->extraCellAttributes(['data-label' => 'Last heartbeat']),
                Tables\Columns\ToggleColumn::make('proxy_mode')
                    ->label('Proxy')
                    ->disabled()
                    ->extraCellAttributes(['data-label' => 'Proxy']),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_activities')
                    ->label('Activities')
                    ->icon('heroicon-m-bolt')
                    ->color('purple')
                    ->url(fn($record) => DeviceResource::getUrl('activities', ['record' => $record])),

                Tables\Actions\ActionGroup::make(
                    DeviceActions::actions()
                )
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
            'activities' => Pages\PetkitActivities::route('/order/{record}/activities'),

        ];
    }
}
