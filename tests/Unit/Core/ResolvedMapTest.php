<?php

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedGroup;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

function navGroup(): ResolvedGroup
{
    return new ResolvedGroup('navigation', held('alt', 'shift'), [
        new ShortcutBinding(
            new ShortcutTarget('navigation', 'products'),
            combo('alt+shift+p'),
        ),
    ]);
}

function tableGroup(): ResolvedGroup
{
    return new ResolvedGroup('table', ModifierScheme::none(), [
        new ShortcutBinding(
            new ShortcutTarget('table', 'search'),
            combo('/'),
        ),
    ]);
}

it('exposes its groups in the order given', function () {
    $map = new ResolvedMap([navGroup(), tableGroup()]);

    expect($map->groups())->toHaveCount(2)
        ->and($map->groups()[0]->setKey)->toBe('navigation')
        ->and($map->groups()[1]->setKey)->toBe('table');
});

it('merges another map by concatenating groups in order', function () {
    $nav = new ResolvedMap([navGroup()]);
    $page = new ResolvedMap([tableGroup()]);

    $merged = $nav->merge($page);

    expect($merged->groups())->toHaveCount(2)
        ->and($merged->groups()[0]->setKey)->toBe('navigation')
        ->and($merged->groups()[1]->setKey)->toBe('table');
});

it('leaves both operands unmutated when merging', function () {
    $nav = new ResolvedMap([navGroup()]);
    $page = new ResolvedMap([tableGroup()]);

    $merged = $nav->merge($page);

    expect($merged)->not->toBe($nav)
        ->and($nav->groups())->toHaveCount(1)
        ->and($page->groups())->toHaveCount(1);
});

it('keeps a leader scheme through the cache round trip', function () {
    $leader = ModifierScheme::leader('g');
    $map = new ResolvedMap([
        new ResolvedGroup('navigation', $leader, [
            new ShortcutBinding(new ShortcutTarget('navigation', 'orders'), new KeyCombo($leader, 'KeyO')),
        ]),
    ]);

    $restored = ResolvedMap::fromCache($map->toCache());

    expect($restored->groups()[0]->modifier)->toEqual($leader)
        ->and($restored->groups()[0]->bindings[0]->keyCombo)->toEqual(new KeyCombo($leader, 'KeyO'));
});
