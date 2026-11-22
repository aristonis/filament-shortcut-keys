<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Filament\FilamentNavigationProvider;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Tests\Support\Pages\AuditLog;
use Aristonis\FilamentShortcutKeys\Tests\Support\Pages\DailyUsage;
use Aristonis\FilamentShortcutKeys\Tests\Support\Pages\UsageReport;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource;
use Filament\Pages\Dashboard;

it('adapts the admin panel resources and pages into navigation items in sidebar order', function () {
    $provider = new FilamentNavigationProvider;

    expect($provider)->toBeInstanceOf(NavigationProvider::class);

    $items = $provider->items('admin');

    // Ordered by navigation sort: Dashboard (-2), Users (1), Orders (2), then the plugin's own
    // reference (100) and manager (101) pages. The hidden Usage report page is left out.
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

it('leaves pages hidden from the sidebar out of the navigation items', function () {
    $keys = array_map(fn (NavItem $item) => $item->structureKey, (new FilamentNavigationProvider)->items('admin'));

    expect($keys)->not->toContain(UsageReport::class);
});

it('appends hidden pages after every visible item when the config includes them', function () {
    config()->set('shortcut-keys.navigation.include_hidden', true);

    $keys = array_map(fn (NavItem $item) => $item->structureKey, (new FilamentNavigationProvider)->items('admin'));

    // Daily usage is hidden too, but its route needs a parameter, so it is skipped rather than
    // failing the whole list. Audit log is hidden from a guest.
    expect(array_slice($keys, 0, 5))->toBe([Dashboard::class, UserResource::class, OrderResource::class, ShortcutReference::class, ManageShortcuts::class])
        ->and(array_slice($keys, 5))->toBe([UsageReport::class, AuditLog::class])
        ->and($keys)->not->toContain(DailyUsage::class);
});

it('refuses an include_hidden value that is not a boolean instead of reading "false" as true', function () {
    config()->set('shortcut-keys.navigation.include_hidden', 'false');

    (new FilamentNavigationProvider)->items('admin');
})->throws(InvalidArgumentException::class);

it('lists every registered target, hidden ones included, for maintenance', function () {
    expect((new FilamentNavigationProvider)->registeredTargets('admin'))
        ->toContain(UsageReport::class, Dashboard::class, UserResource::class, OrderResource::class);
});

it('changes the version token when hidden pages are included', function () {
    $before = (new FilamentNavigationProvider)->versionToken('admin');

    config()->set('shortcut-keys.navigation.include_hidden', true);

    expect((new FilamentNavigationProvider)->versionToken('admin'))->not->toBe($before);
});
