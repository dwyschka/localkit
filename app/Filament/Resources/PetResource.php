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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Forms\Components\Select::make('Sterilised')->options([
                    0 => 'no',
                    1 => 'yes',
                ])->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('species')->searchable(),
                Tables\Columns\TextColumn::make('gender')->formatStateUsing(function(string $state) {
                    return $state === '0' ? 'Male' : 'Female';
                })->searchable(),
                Tables\Columns\TextColumn::make('weight')->searchable(),
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
