<?php

use Aristonis\FilamentShortcutKeys\Authorization\AuthorityGate;
use Aristonis\FilamentShortcutKeys\Authorization\ConfigAuthorityGate;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Filament\FilamentNavigationProvider;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;

it('merges the package config so shortcut-keys.* resolves through the container', function () {
    expect(config('shortcut-keys.customization'))->toBe('personal')
        ->and(config('shortcut-keys.cache.ttl'))->toBeNull();
});

it('binds the navigation port to the Filament adapter', function () {
    expect(app(NavigationProvider::class))->toBeInstanceOf(FilamentNavigationProvider::class);
});

it('binds the map repository to the Eloquent adapter', function () {
    expect(app(MapRepository::class))->toBeInstanceOf(EloquentMapRepository::class);
});

it('binds the authority gate to the config-backed adapter', function () {
    expect(app(AuthorityGate::class))->toBeInstanceOf(ConfigAuthorityGate::class);
});

it('binds the ports as singletons', function () {
    expect(app(NavigationProvider::class))->toBe(app(NavigationProvider::class))
        ->and(app(MapRepository::class))->toBe(app(MapRepository::class));
});
