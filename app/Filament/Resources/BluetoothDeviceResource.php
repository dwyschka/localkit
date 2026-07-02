<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BluetoothDeviceResource\Pages;
use App\Filament\Resources\BluetoothDeviceResource\RelationManagers;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\BluetoothDevices\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BluetoothDeviceResource extends Resource
{
    protected static ?string $model = BluetoothDevice::class;

    protected static ?string $navigationIcon = 'icon-bluetooth';

    public static function form(Form $form): Form
    {
        $model = $form->getModelInstance();

        $general = Forms\Components\Tabs\Tab::make('General')
            ->icon('heroicon-o-cog-6-tooth')
            ->columns(2)
            ->schema([
                Forms\Components\TextInput::make('name'),
                Forms\Components\Select::make('type')->required()->options([
                    'k3' => 'K3 Spray',
                    'w5' => 'Eversweet Fountain',
                ]),
                Forms\Components\TextInput::make('mac')->required(),
                Forms\Components\TextInput::make('petkit_id')->required(),
                Forms\Components\TextInput::make('secret')->required(),
                Forms\Components\TextInput::make('serial_number'),
                Forms\Components\Fieldset::make('Proxy settings')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('interval')
                            ->helperText('The interval in minutes to check the device status')
                            ->numeric(true)->minValue(10)
                            ->hidden(fn ($record) => $record?->type == 'k3'),
                        Forms\Components\Select::make('link_with')
                            ->helperText('Set the device to which the proxy is linked')
                            ->relationship('linkWith', 'name', fn ($query, $record) => $record?->type == 'k3' ? $query->whereIn('device_type', ['t4']) : $query),
                    ]),
            ]);

        $config = ($model->exists && $model->type) ? ($model->ui()?->formFields() ?? []) : [];
        $configTabs = \App\Filament\Resources\DeviceResource::normalizeConfigTabs($config);

        return $form->schema([
            Forms\Components\Tabs::make('Bluetooth')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs(array_merge([$general], $configTabs)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Device')
                    ->searchable()->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->icon('heroicon-o-signal')
                    ->iconColor('primary')
                    ->extraCellAttributes(['class' => 'lk-cardhead']),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'k3' => 'K3 Spray',
                        'w5' => 'Eversweet Fountain',
                        default => $state,
                    })
                    ->extraCellAttributes(['data-label' => 'Type']),
                Tables\Columns\TextColumn::make('mac')
                    ->label('MAC')
                    ->color('gray')
                    ->fontFamily(\Filament\Support\Enums\FontFamily::Mono)
                    ->extraCellAttributes(['data-label' => 'MAC']),
                Tables\Columns\TextColumn::make('link_with')
                    ->label('Link with')
                    ->badge()
                    ->formatStateUsing(function (?int $state) {
                        if (empty($state)) {
                            return 'None';
                        }
                        return Device::find($state)->name ?? 'None';
                    })
                    ->color(fn (?int $state): string => empty($state) ? 'gray' : 'info')
                    ->extraCellAttributes(['data-label' => 'Link with']),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ActionGroup::make(
                    Actions::actions()
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
            'index' => Pages\ListBluetoothDevices::route('/'),
            'create' => Pages\CreateBluetoothDevice::route('/create'),
            'edit' => Pages\EditBluetoothDevice::route('/{record}/edit'),
        ];
    }
}
