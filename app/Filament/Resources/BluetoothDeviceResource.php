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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->columnSpan('half'),
                Forms\Components\Select::make('type')->required()->options([
                    'k3' => 'K3 Spray',
                    'w5' => 'Eversweet Fountain'
                ]),
                Forms\Components\TextInput::make('mac')->required()->columnSpan('half'),
                Forms\Components\TextInput::make('secret')->required()->columnSpan('half'),
                Forms\Components\TextInput::make('petkit_id')->required()->columnSpan('half'),
                Forms\Components\TextInput::make('serial_number')->columnSpan('half'),

                Forms\Components\Fieldset::make('Proxy Settings')->schema([
                    Forms\Components\TextInput::make('interval')
                        ->helperText('The interval in minutes to check the device status')
                        ->numeric(true)->minValue(10)
                        ->hidden(fn($record) => $record->type == "k3")
                    ,
                    Forms\Components\Select::make('link_with')
                        ->helperText('Set the Device to which the Proxy is linked')
                    ->relationship('linkWith', 'name', fn($query, $record) => $record->type == "k3" ? $query->whereIn('device_type', [ 't4' ]) : $query)
                ]),

                Forms\Components\Fieldset::make('Device Configuration')->schema([
                    ...$form->getModelInstance()->ui()?->formFields() ?? [],
                ])->hiddenOn('create')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type')->searchable()->formatStateUsing(function(string $state) {
                    if($state === 'k3') {
                        return 'K3 Spray';
                    }
                    return $state;
                }),
                Tables\Columns\TextColumn::make('mac')->searchable(),
                Tables\Columns\TextColumn::make('link_with')
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
