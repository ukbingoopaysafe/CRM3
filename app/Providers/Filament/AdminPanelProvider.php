<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * One Arabic, right-to-left panel. Rental and resale never merge: each
 * department owns its own navigation group and its own tables (D-020, D-078).
 */
class AdminPanelProvider extends PanelProvider
{
    /**
     * Full ramps, never a single hex: Filament derives the shades it needs from
     * the value it is given, and the derived primary landed on a bright cyan
     * that broke the palette. Every step below is a deliberate teal.
     */
    private const TEAL = [
        50 => '#F1F8F9',
        100 => '#DDEDEF',
        200 => '#B9D8DD',
        300 => '#8CBCC4',
        400 => '#57A0AC',
        500 => '#2C7C8B',
        600 => '#0F4C5C',
        700 => '#0D4251',
        800 => '#0B3744',
        900 => '#092C37',
        950 => '#04191F',
    ];

    private const AQUA = [
        50 => '#F0F9FA',
        100 => '#D9F0F3',
        200 => '#B3E0E7',
        300 => '#7FC9D5',
        400 => '#4AABB9',
        500 => '#1A6B7A',
        600 => '#155A67',
        700 => '#124A55',
        800 => '#0F3B44',
        900 => '#0B2C33',
        950 => '#061A1F',
    ];

    private const GREEN = [
        50 => '#ECFDF3',
        100 => '#D1FADF',
        200 => '#A6F4C5',
        300 => '#6CE9A6',
        400 => '#32D583',
        500 => '#12B76A',
        600 => '#039855',
        700 => '#15803D',
        800 => '#106B33',
        900 => '#0C5429',
        950 => '#053321',
    ];

    private const GOLD = [
        50 => '#FDF9EC',
        100 => '#FAF0CE',
        200 => '#F4E19D',
        300 => '#EBCE63',
        400 => '#DFBB3C',
        500 => '#D4AF37',
        600 => '#B8932A',
        700 => '#967422',
        800 => '#7A5D1F',
        900 => '#654D1E',
        950 => '#3B2B0F',
    ];

    private const RED = [
        50 => '#FEF3F2',
        100 => '#FEE4E2',
        200 => '#FECDCA',
        300 => '#FDA29B',
        400 => '#F97066',
        500 => '#F04438',
        600 => '#D92D20',
        700 => '#B42318',
        800 => '#912018',
        900 => '#7A271A',
        950 => '#55160C',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->brandName('Cible')
                    ->recoverable(),
            ])
            ->brandName('Cible')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->colors([
                'primary' => self::TEAL,
                'info' => self::AQUA,
                'success' => self::GREEN,
                'warning' => self::GOLD,
                'danger' => self::RED,
                'gray' => Color::Slate,
            ])
            ->navigationGroups([
                NavigationGroup::make('الإيجار'),
                NavigationGroup::make('إعادة البيع'),
                NavigationGroup::make('الإدارة'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('16rem')
            ->collapsedSidebarWidth('4.5rem')
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
