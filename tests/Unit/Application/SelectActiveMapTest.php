<?php

use Aristonis\FilamentShortcutKeys\Application\SelectActiveMap;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryMapSelector;

it('selects a map in personal mode', function () {
    $selector = new InMemoryMapSelector;

    $id = (new SelectActiveMap($selector, 'personal'))->select('App\\User', '1', 'admin', 7);

    expect($id)->toBe(7)
        ->and($selector->selected)->toHaveCount(1)
        ->and($selector->selected[0])->toBe(['authType' => 'App\\User', 'authId' => '1', 'panelId' => 'admin', 'mapId' => 7]);
});

it('refuses to select a map in locked mode', function () {
    $selector = new InMemoryMapSelector;

    expect(fn () => (new SelectActiveMap($selector, 'locked'))->select('App\\User', '1', 'admin', 7))
        ->toThrow(EditingLockedException::class);

    expect($selector->selected)->toBeEmpty();
});
