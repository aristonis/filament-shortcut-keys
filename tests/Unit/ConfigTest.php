<?php

it('publishes a config array with the documented keys and defaults', function () {
    $config = require __DIR__ . '/../../config/shortcut-keys.php';

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['customization', 'modifiers', 'authorize', 'overlay', 'cache'])
        ->and($config['customization'])->toBe('personal')
        ->and($config['authorize'])->toBeNull()
        ->and($config['overlay'])->toBe([])
        ->and($config['cache'])->toBe(['ttl' => 86400])
        ->and($config['modifiers'])->toBe([
            'navigation' => ['alt', 'shift'],
            'global' => ['alt'],
            'table' => [],
            'row-action' => [],
            'custom' => ['alt', 'shift'],
        ]);
});

it('ships a finite cache lifetime so old keymaps are reclaimed', function () {
    $ttl = require __DIR__ . '/../../config/shortcut-keys.php';

    // The fingerprint key already guarantees freshness: it rotates whenever the navigation version,
    // the active map's version, the overlay or the locale changes. What it does NOT do is delete the
    // key it rotated away from, and nothing else in the package deletes it either. Caching forever
    // therefore leaks one entry per edit and per deploy, permanently, on a store that never expires
    // them. A finite lifetime costs an occasional recompute of a map that is cheap to rebuild.
    expect($ttl['cache']['ttl'])->toBeInt()->toBeGreaterThan(0);
});
