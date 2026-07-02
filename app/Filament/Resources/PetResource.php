<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PetResource\Pages;
use App\Filament\Resources\PetResource\RelationManagers;
use App\Models\Pet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PetResource extends Resource
{
    protected static ?string $model = Pet::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->columnSpan('half')->required(),
                Forms\Components\TextInput::make('weight')->numeric(true)->columnSpan('half')->required(),

                Forms\Components\DatePicker::make('birthdate')->columnSpan('half')->required(),
                Forms\Components\TextInput::make('species')->columnSpan('half')->required(),

                Forms\Components\Select::make('gender')->options([
                    0 => 'Male',
                    1 => 'Female',
                ])->required(),
                Forms\Components\Select::make('sterilised')->options([
                    false => 'no',
                    true => 'yes',
                ])->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Pet')
                    ->searchable()->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->description(fn ($record) => $record->species)
                    ->icon('heroicon-o-heart')
                    ->iconColor('primary')
                    ->extraCellAttributes(['class' => 'lk-cardhead']),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === '0' ? 'Male' : 'Female')
                    ->extraCellAttributes(['data-label' => 'Gender']),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Weight')
                    ->formatStateUsing(fn ($state): string => $state . ' kg')
                    ->extraCellAttributes(['data-label' => 'Weight']),
                Tables\Columns\TextColumn::make('birthdate')
                    ->label('Birthdate')
                    ->date('Y-m-d')->color('gray')
                    ->extraCellAttributes(['data-label' => 'Birthdate']),
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
            'index' => Pages\ListPets::route('/'),
            'create' => Pages\CreatePet::route('/create'),
            'edit' => Pages\EditPet::route('/{record}/edit'),
        ];
    }
}
