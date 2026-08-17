<?php

namespace App\Filament\Resources\PetResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\PetResource;
use App\Jobs\PublishDiscernUpdate;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPet extends EditRecord
{
    protected static string $resource = PetResource::class;

    /**
     * @var array<int, string>
     */
    protected array $pendingImages = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['images'] = $this->getRecord()->images->pluck('path')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingImages = $data['images'] ?? [];
        unset($data['images']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->syncImages($this->pendingImages)) {
            PublishDiscernUpdate::dispatchForAllDevices();
        }
    }
}
