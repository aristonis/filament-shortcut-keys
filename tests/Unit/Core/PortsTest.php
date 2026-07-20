<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryMapRepository;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

it('NavItem carries structural key, label and url', function () {
    $item = new NavItem('App\\Filament\\Resources\\ProductResource', 'Products', '/admin/products');

    expect($item->structureKey)->toBe('App\\Filament\\Resources\\ProductResource')
        ->and($item->label)->toBe('Products')
        ->and($item->url)->toBe('/admin/products');
});

it('ActionTarget carries name, label and selector', function () {
    $action = new ActionTarget('export', 'Export', '[wire\\:key="export"]');

    expect($action->name)->toBe('export')
        ->and($action->label)->toBe('Export')
        ->and($action->selector)->toBe('[wire\\:key="export"]');
});

it('the fake navigation provider returns seeded items and version token', function () {
    $provider = new InMemoryNavigationProvider(
        items: [new NavItem('App\\X', 'Products', '/x')],
        versionToken: 'v1',
    );

    expect($provider->items('admin'))->toHaveCount(1)
        ->and($provider->items('admin')[0]->structureKey)->toBe('App\\X')
        ->and($provider->versionToken('admin'))->toBe('v1');
});

it('the fake page context returns actions and table presence', function () {
    $context = new InMemoryPageContextProvider(
        actions: [new ActionTarget('create', 'Create', '#create')],
        hasTable: true,
    );

    expect($context->actions())->toHaveCount(1)
        ->and($context->hasTable())->toBeTrue();
});

it('the fake map repository returns the seeded active map', function () {
    $map = new MapData(type: MapType::SYSTEM, modifiers: null, entries: [], version: 3);
    $repo = new InMemoryMapRepository($map);

    $active = $repo->activeMap('App\\Models\\User', '1', 'admin');

    expect($active->type)->toBe(MapType::SYSTEM)
        ->and($active->version)->toBe(3)
        ->and($active->entries)->toBe([]);
});
