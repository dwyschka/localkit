<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDevice extends EditRecord
{
    protected static string $resource = DeviceResource::class;

    /**
     * As of 2026-08-24, configuration['schedule'] only holds a pointer
     * (['key' => ..., 'checksum' => ...]) - the actual day-groups the
     * schedule Repeater edits live in device_schedules/device_schedule_items
     * (Device::scheduleGroups()/syncSchedule()). Only feeder device types
     * have a schedule at all - method_exists(..., 'toFeed') is the same
     * discriminator SetProperty::handle() already uses for that.
     */
    private function hasSchedule(Device $record): bool
    {
        return method_exists($record->definition(), 'toFeed');
    }

    /**
     * Swaps the stored pointer back out for the real day-groups before the
     * form fills, so the schedule Repeater has something to render.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        if ($this->hasSchedule($record) && isset($data['configuration']) && is_array($data['configuration'])) {
            $key = $data['configuration']['schedule']['key'] ?? 'default';
            $data['configuration']['schedule'] = $record->scheduleGroups($key);
        }

        return $data;
    }

    /**
     * Persists whatever the Repeater submitted into device_schedules/
     * device_schedule_items, then replaces $data['configuration']['schedule']
     * with a pointer before it reaches $record->update() - the checksum is
     * what lets the existing JsonHelper::difference()-based schedule-changed
     * detection in each device type's propertyChange() keep working even
     * though 'key' itself usually doesn't change between saves. This runs
     * before handleRecordUpdate(), so by the time propertyChange() reads the
     * schedule back via scheduleGroups(), the tables are already up to date.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if ($this->hasSchedule($record) && isset($data['configuration']['schedule']) && is_array($data['configuration']['schedule'])) {
            // The CheckboxList('re') field dehydrates 're' as an array, not a
            // comma string - joining it here, after validation, instead of in
            // the field's own dehydrateStateUsing() is what lets Filament's
            // built-in "is one of the options" check validate a multi-day
            // selection correctly (see the UI.php comment next to that field).
            $groups = array_map(fn(array $group) => [
                ...$group,
                're' => implode(',', (array) ($group['re'] ?? [])),
            ], $data['configuration']['schedule']);
            $key = $record->configuration['schedule']['key'] ?? 'default';

            $record->syncSchedule($groups, $key);

            $data['configuration']['schedule'] = [
                'key' => $key,
                'checksum' => md5(json_encode($groups)),
            ];
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetConfig')
                ->label('Reset Config')
                ->color('danger')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Reset configuration to defaults?')
                ->modalDescription('This overwrites the stored configuration for this device with its type\'s defaults - schedule, settings, everything - and pushes the reset to the device. This cannot be undone.')
                ->action(function (Device $record) {
                    // "schedule, settings, everything" (see modalDescription
                    // above) - the schedule tables aren't part of the
                    // 'configuration' column being overwritten below, so they
                    // need their own explicit clear.
                    if ($this->hasSchedule($record)) {
                        $record->syncSchedule([], $record->configuration['schedule']['key'] ?? 'default');
                    }

                    $record->update([
                        'configuration' => $record->definition()->resetConfiguration(),
                    ]);

                    Notification::make()
                        ->title('Configuration reset to defaults')
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            DeleteAction::make(),
        ];
    }
}
