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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PetkitPanelProvider extends PanelProvider
{
    private const ACTIVITIES_STYLESHEETS = [
        'css/petkit-activities.css',
        'css/petkit-timeline.css',
        'css/petkit-activity-detail.css',
        'css/petkit-event-counts.css',
    ];
    private const ACTIVITIES_SCRIPTS = [
        'js/petkit-activities.js',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('petkit')
            ->path('')
            ->login()
            ->topNavigation()
            ->breadcrumbs(false)
            ->navigationGroups([
                'Activities',
                'System',
            ])
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
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => loadInlineStylesheet(...self::ACTIVITIES_STYLESHEETS),
            )
            ->renderHook(
                \Filament\View\PanelsRenderHook::BODY_END,
                fn (): string => loadInlineScript(...self::ACTIVITIES_SCRIPTS),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
