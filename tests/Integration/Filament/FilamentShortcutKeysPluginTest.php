<?php

use Aristonis\FilamentShortcutKeys\FilamentShortcutKeysPlugin;

it('stores and returns the registered row-action names', function () {
    $plugin = FilamentShortcutKeysPlugin::make()->rowActions(['approve', 'reject']);

    expect($plugin->getRowActions())->toBe(['approve', 'reject']);
});

it('defaults to no registered row actions', function () {
    expect(FilamentShortcutKeysPlugin::make()->getRowActions())->toBe([]);
});

it('exposes the row-action names registered on the panel via filament()', function () {
    // The admin test panel registers the plugin with ->rowActions(['approve', 'reject']).
    expect(filament('filament-shortcut-keys')->getRowActions())->toBe(['approve', 'reject']);
});
