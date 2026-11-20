<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

it('builds a stable identity from set and structural key', function () {
    $target = new ShortcutTarget('navigation', 'App\\Filament\\Resources\\ProductResource');

    expect($target->identity())->toBe('navigation:App\\Filament\\Resources\\ProductResource');
});
