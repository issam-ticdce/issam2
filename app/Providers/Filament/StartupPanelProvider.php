<?php

namespace App\Providers\Filament;

use App\Filament\Startup\Pages\EditProfile;
use App\Http\Middleware\SetPanelLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Espace des startups : /espace (comptes créés par le TICDCE, pas d'inscription libre). */
class StartupPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('startup')
            ->path('espace')
            ->login()
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->brandName('TICDCE Marketplace')
            ->brandLogo(asset('images/logo-mark.png'))
            ->brandLogoHeight('2.2rem')
            ->favicon(asset('favicon.png'))
            ->colors([
                'primary' => Color::hex('#123a63'),
                'info' => Color::hex('#16788a'),
            ])
            ->font('Inter')
            ->userMenuItems([
                Action::make('site')->label(fn () => __('space.nav.view_site'))->icon(Heroicon::OutlinedGlobeAlt)
                    ->url(fn () => route('home', ['locale' => app()->getLocale()]), shouldOpenInNewTab: true),
            ])
            ->discoverResources(in: app_path('Filament/Startup/Resources'), for: 'App\Filament\Startup\Resources')
            ->discoverWidgets(in: app_path('Filament/Startup/Widgets'), for: 'App\Filament\Startup\Widgets')
            ->pages([
                Dashboard::class,
            ])
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
            // Aussi appliqué aux requêtes Livewire, pour garder la langue de l'utilisateur.
            ->middleware([SetPanelLocale::class], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
