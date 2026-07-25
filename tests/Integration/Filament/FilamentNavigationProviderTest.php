<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Filament\FilamentNavigationProvider;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource;
use Filament\Pages\Dashboard;

it('adapts the admin panel resources and pages into navigation items in sidebar order', function () {
    $provider = new FilamentNavigationProvider;

    expect($provider)->toBeInstanceOf(NavigationProvider::class);

    $items = $provider->items('admin');

    // Ordered by navigation sort: Dashboard (-2), Users (1), Orders (2), then the plugin's own
    // reference (100) and manager (101) pages.
    expect($items)->toHaveCount(5)
        ->and($items)->each->toBeInstanceOf(NavItem::class);

    [$dashboard, $users, $orders, $reference, $manage] = $items;

    expect($dashboard->structureKey)->toBe(Dashboard::class)
        ->and($users->structureKey)->toBe(UserResource::class)
        ->and($users->label)->toBe(UserResource::getNavigationLabel())
        ->and($orders->structureKey)->toBe(OrderResource::class)
        ->and($orders->label)->toBe(OrderResource::getNavigationLabel())
        ->and($reference->structureKey)->toBe(ShortcutReference::class)
        ->and($manage->structureKey)->toBe(ManageShortcuts::class);
});

it('builds a url for both resources (getIndexUrl) and pages (getUrl)', function () {
    $items = (new FilamentNavigationProvider)->items('admin');

    // Every nav item resolves a non-empty url; pages and resources use different Filament methods.
    expect($items)->each(fn ($item) => $item->url->toBeString()->not->toBeEmpty());
});

it('returns a non-empty version token that is stable across calls', function () {
    $provider = new FilamentNavigationProvider;

    $token = $provider->versionToken('admin');

    expect($token)->toBeString()->not->toBeEmpty()
        ->and($provider->versionToken('admin'))->toBe($token);
});
