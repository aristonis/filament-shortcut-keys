<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Panel;

use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Widgets\PendingOrders;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

/**
 * Registers a minimal `admin` panel for integration tests so adapters can read real panel
 * internals (registered resources, navigation order, row actions). Two resources are wired
 * in with explicit navigation sorts to give ordering-sensitive assertions a stable target.
 */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([
                UserResource::class,
                OrderResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                PendingOrders::class,
            ])
            ->plugin(
                FilamentShortcutKeysPlugin::make()->rowActions(['approve', 'reject']),
            );
    }
}
