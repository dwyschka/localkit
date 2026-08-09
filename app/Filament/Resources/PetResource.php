<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PetResource\Pages;
use App\Filament\Resources\PetResource\RelationManagers;
use App\Models\Pet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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
                Forms\Components\FileUpload::make('images')
                    ->label('Photos')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('pets')
                    ->visibility('public')
                    ->columnSpanFull(),

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
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->columns([
                Stack::make([
                    // 1. Name (with gender badge)
                    Split::make([
                        Tables\Columns\TextColumn::make('name')
                            ->searchable()
                            ->weight(FontWeight::Bold)
                            ->size(Tables\Columns\TextColumn\TextColumnSize::Large),
                        Tables\Columns\TextColumn::make('gender')
                            ->badge()
                            ->grow(false)
                            ->color(fn(string $state): string => $state === '0' ? 'info' : 'pink')
                            ->formatStateUsing(fn(string $state) => $state === '0' ? 'Male' : 'Female'),
                    ]),

                    // 2. Photo (first image only)
                    Tables\Columns\ImageColumn::make('images')
                        ->label('Photo')
                        ->disk('public')
                        // `images` is an array of paths — only show the first photo on the card.
                        ->state(fn(Pet $record): ?string => $record->images[0] ?? null)
                        ->height(176)
                        ->extraImgAttributes(['class' => 'w-full rounded-lg object-cover'])
                        ->alignment('center')
                        ->defaultImageUrl(url('https://ui-avatars.com/api/?name=Pet&background=27272a&color=f6ad0f')),

                    // 3. Metadata
                    Split::make([
                        Tables\Columns\TextColumn::make('species')
                            ->searchable()
                            ->color('gray')
                            ->icon('heroicon-m-sparkles'),
                        Tables\Columns\TextColumn::make('weight')
                            ->searchable()
                            ->color('gray')
                            ->icon('heroicon-m-scale')
                            ->suffix(' kg')
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
                    ->size('sm'),
            ])
            ->actionsAlignment('left')
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
