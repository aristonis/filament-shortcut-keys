<?php

it('publishes a config array with the documented keys and defaults', function () {
    $config = require __DIR__ . '/../../config/shortcut-keys.php';

    expect($config)->toBeArray()
        ->and($config)->toHaveKeys(['customization', 'modifiers', 'authorize', 'overlay', 'cache'])
        ->and($config['customization'])->toBe('personal')
        ->and($config['authorize'])->toBeNull()
        ->and($config['overlay'])->toBe([])
        ->and($config['cache'])->toBe(['ttl' => null])
        ->and($config['modifiers'])->toBe([
            'navigation' => ['alt', 'shift'],
            'global' => ['alt'],
            'table' => [],
            'row-action' => [],
            'custom' => ['alt', 'shift'],
        ]);
});
