<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DevicesOverview;
use App\Filament\Widgets\OverviewStats;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            OverviewStats::class,
            DevicesOverview::class,
        ];
    }
}
