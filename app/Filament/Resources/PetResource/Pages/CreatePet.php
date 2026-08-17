<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Resources\PetResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePet extends CreateRecord
{
    protected static string $resource = PetResource::class;

    /**
     * @var array<int, string>
     */
    protected array $pendingImages = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingImages = $data['images'] ?? [];
        unset($data['images']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->syncImages($this->pendingImages);
    }
}
