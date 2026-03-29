<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use App\Models\Device;
use App\Models\History;
use App\Models\User;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use JaOcero\ActivityTimeline\Components\ActivityDate;
use JaOcero\ActivityTimeline\Components\ActivityDescription;
use JaOcero\ActivityTimeline\Components\ActivityIcon;
use JaOcero\ActivityTimeline\Components\ActivitySection;
use JaOcero\ActivityTimeline\Components\ActivityTitle;
use JaOcero\ActivityTimeline\Enums\IconAnimation;
use JaOcero\ActivityTimeline\Pages\ActivityTimelinePage;

class PetkitActivities extends Page
{
    use InteractsWithRecord;

    protected static string $resource = DeviceResource::class;

    protected static string $view = 'filament.resources.device-resource.pages.petkit-activities';


    public function mount(int|string $record)
    {

        $this->record = $this->resolveRecord($record);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ActivitySection::make('histories')
                    ->headingVisible(false)
                    ->schema([
                        ActivityTitle::make('title')
                            ->state(fn(History $record): string => $record->title())
                            ->label('Title')
                            ->placeholder('No title is set')
                            ->allowHtml(), // Be aware that you will need to ensure that the HTML is safe to render, otherwise your application will be vulnerable to XSS attacks.
                        ActivityDescription::make('description')
                            ->state(fn(History $record): string => $record->message())
                            ->placeholder('No description is set')
                            ->allowHtml(),
                        ActivityDate::make('created_at')
                            ->date('F j, Y', 'Europe/Berlin')
                            ->placeholder('No date is set.'),
                        ActivityIcon::make('type')
                            ->icon(fn(string|null $state): string|null => match ($state) {
                                'IN_USE' => 'heroicon-m-exclamation-triangle',
                                'CLEANING' => 'heroicon-m-arrow-path-rounded-square',
                                'MAINTENANCE' => 'heroicon-m-wrench-screwdriver',
                                default => 'heroicon-m-exclamation-circle',
                            })
                            ->color(fn(string|null $state): string|null => match ($state) {
                                'MAINTENANCE' => 'info',
                                'CLEANING' => 'info',
                                'IN_USE' => 'warning',
                                default => 'gray',
                            }),
                    ])
                    ->showItemsCount(16) // Show up to 2 items
                    ->showItemsLabel('View Old') // Show "View Old" as link label
                    ->showItemsIcon('heroicon-m-chevron-down') // Show button icon
                    ->showItemsColor('gray') // Show button color and it supports all colors
                    ->aside(false)
                    ->headingVisible(false) // make heading visible or not
                    ->extraAttributes(['class' => 'my-new-class']) // add extra class
            ])
            ->record($this->record);
    }

}
