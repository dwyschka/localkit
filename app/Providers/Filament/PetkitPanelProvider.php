<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PetkitPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Filament otherwise versions app assets with its own package version.
        $assetFiles = [
            'css/petkit-activities.css',
            'css/petkit-timeline.css',
            'css/petkit-pet-activities.css',
            'css/petkit-activity-detail.css',
            'css/petkit-event-counts.css',
            'css/petkit-recent-activity.css',
            'js/petkit-activities.js',
        ];
        FilamentAsset::appVersion(hash('sha256', implode('', array_map(
            fn (string $file): string => hash_file('sha256', resource_path($file)),
            $assetFiles,
        ))));

        return $panel
            ->default()
            ->id('petkit')
            ->path('')
            ->login()
            ->topNavigation()
            ->breadcrumbs(false)
            ->colors([
                'primary' => Color::Amber,
                'purple' => Color::Purple,
                'pink' => Color::Pink,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->assets([
                Css::make('petkit-activities', resource_path('css/petkit-activities.css')),
                Css::make('petkit-timeline', resource_path('css/petkit-timeline.css'))->loadedOnRequest(),
                Css::make('petkit-pet-activities', resource_path('css/petkit-pet-activities.css'))->loadedOnRequest(),
                Css::make('petkit-activity-detail', resource_path('css/petkit-activity-detail.css'))->loadedOnRequest(),
                Css::make('petkit-event-counts', resource_path('css/petkit-event-counts.css'))->loadedOnRequest(),
                Css::make('petkit-recent-activity', resource_path('css/petkit-recent-activity.css'))->loadedOnRequest(),
                Js::make('petkit-activities', resource_path('js/petkit-activities.js')),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
