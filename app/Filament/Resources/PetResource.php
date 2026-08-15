<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\ImageColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\PetResource\Pages\CreatePet;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\PetResource\Pages;
use App\Filament\Resources\PetResource\RelationManagers;
use App\Models\Pet;
use Filament\Forms;
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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('images')
                    ->label('Photos')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->imageEditorViewportWidth('360')
                    ->imageEditorViewportHeight('360')
                    ->imageResizeMode('cover')
                    ->imageResizeTargetWidth(360)
                    ->imageResizeTargetHeight(360)
                    ->disk('public')
                    ->directory('pets')
                    ->visibility('public')
                    ->columnSpanFull(),

                TextInput::make('name')->columnSpan('half')->required(),
                ColorPicker::make('color')->columnSpan('half'),

                TextInput::make('weight')->numeric(true)->columnSpan('half')->required(),

                DatePicker::make('birthdate')->columnSpan('half')->required(),
                TextInput::make('species')->columnSpan('half')->required(),

                Select::make('gender')->options([
                    0 => 'Male',
                    1 => 'Female',
                ])->required(),
                Select::make('sterilised')->options([
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
                        TextColumn::make('name')
                            ->searchable()
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large),
                        TextColumn::make('gender')
                            ->badge()
                            ->grow(false)
                            ->color(fn(string $state): string => $state === '0' ? 'info' : 'pink')
                            ->formatStateUsing(fn(string $state) => $state === '0' ? 'Male' : 'Female'),
                    ]),

                    // 2. Photo (first image only)
                    ImageColumn::make('images')
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
                        TextColumn::make('species')
                            ->searchable()
                            ->color('gray')
                            ->icon('heroicon-m-sparkles'),
                        TextColumn::make('weight')
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
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->size('sm'),
            ])
            ->recordActionsAlignment('start')
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
            'index' => ListPets::route('/'),
            'create' => CreatePet::route('/create'),
            'edit' => EditPet::route('/{record}/edit'),
        ];
    }
}
