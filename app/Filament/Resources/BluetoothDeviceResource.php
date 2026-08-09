<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\BluetoothDeviceResource\Pages\ListBluetoothDevices;
use App\Filament\Resources\BluetoothDeviceResource\Pages\CreateBluetoothDevice;
use App\Filament\Resources\BluetoothDeviceResource\Pages\EditBluetoothDevice;
use App\Filament\Resources\BluetoothDeviceResource\Pages;
use App\Filament\Resources\BluetoothDeviceResource\RelationManagers;
use App\Models\BluetoothDevice;
use App\Models\Device;
use App\Petkit\BluetoothDevices\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BluetoothDeviceResource extends Resource
{
    protected static ?string $model = BluetoothDevice::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->columnSpan('half'),
                Select::make('type')->required()->options([
                    'k3' => 'K3 Spray',
                    'w5' => 'Eversweet Fountain'
                ]),
                TextInput::make('mac')->required()->columnSpan('half'),
                TextInput::make('secret')->required()->columnSpan('half'),
                TextInput::make('petkit_id')->required()->columnSpan('half'),
                TextInput::make('serial_number')->columnSpan('half'),

                Fieldset::make('Proxy Settings')->schema([
                    TextInput::make('interval')
                        ->helperText('The interval in minutes to check the device status')
                        ->numeric(true)->minValue(10)
                        ->hidden(fn($record) => $record?->type == "k3")
                    ,
                    Select::make('link_with')
                        ->helperText('Set the Device to which the Proxy is linked')
                        ->relationship(
                            name: 'linkWith',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query, Get $get): Builder =>
                            $get('type') === 'k3'
                                ? $query->whereIn('device_type', ['t4'])
                                : $query,
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn ($record): string => $record->name ?? $record->device_type ?? "Device #{$record->getKey()}"
                        )
                ]),

                Fieldset::make('Device Configuration')->schema([
                    ...$schema->getModelInstance()->ui()?->formFields() ?? [],
                ])->hiddenOn('create')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->searchable()->formatStateUsing(function(?string $state) {
                    if($state === 'k3') {
                        return 'K3 Spray';
                    }
                    return $state;
                }),
                TextColumn::make('mac')->searchable(),
                TextColumn::make('link_with')
                    ->badge()
                    ->formatStateUsing(function (?int $state) {
                        if(empty($state)) {
                            return 'None';
                        }
                        return Device::find($state)->name ?? 'None';

                    })
                    ->color('info')
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make(
                    Actions::actions()
                )
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListBluetoothDevices::route('/'),
            'create' => CreateBluetoothDevice::route('/create'),
            'edit' => EditBluetoothDevice::route('/{record}/edit'),
        ];
    }
}
