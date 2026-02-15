<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BluetoothDeviceResource\Pages;
use App\Filament\Resources\BluetoothDeviceResource\RelationManagers;
use App\Models\BluetoothDevice;
use App\Petkit\Devices\PetkitFreshElementSolo;
use App\Petkit\Devices\PetkitPuraMax;
use App\Petkit\Devices\PetkitYumshareSolo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
