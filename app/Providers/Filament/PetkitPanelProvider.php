<?php

namespace App\Providers\Filament;

use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentLaravelLog\FilamentLaravelLogPlugin;

class PetkitPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('petkit')
            ->path('')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->brandName('localkit')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('images/logo.svg'))
            ->darkMode(true)
            ->defaultThemeMode(\Filament\Enums\ThemeMode::Dark)
            ->maxContentWidth(\Filament\Support\Enums\MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
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
            ->plugins([
                FilamentLaravelLogPlugin::make()
            ])
            ->viteTheme('resources/css/filament/petkit/theme.css')
            ->renderHook(
                \Filament\View\PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.sidebar-user'),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
