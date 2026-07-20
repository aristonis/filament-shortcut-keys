<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

it('builds a stable identity from set and structural key', function () {
    $target = new ShortcutTarget('navigation', 'App\\Filament\\Resources\\ProductResource');

    expect($target->identity())->toBe('navigation:App\\Filament\\Resources\\ProductResource');
});

it('is equal when set and structural key match', function () {
    $a = new ShortcutTarget('global', 'export');
    $b = new ShortcutTarget('global', 'export');

    expect($a->equals($b))->toBeTrue();
});

it('differs when the set differs but the key is the same', function () {
    $a = new ShortcutTarget('global', 'export');
    $b = new ShortcutTarget('table', 'export');

    expect($a->equals($b))->toBeFalse();
});

it('differs when the structural key differs', function () {
    $a = new ShortcutTarget('navigation', 'App\\Filament\\Resources\\ProductResource');
    $b = new ShortcutTarget('navigation', 'App\\Filament\\Resources\\OrderResource');

    expect($a->equals($b))->toBeFalse();
});
