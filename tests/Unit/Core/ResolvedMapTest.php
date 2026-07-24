<?php

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedGroup;
use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

function navGroup(): ResolvedGroup
{
    return new ResolvedGroup('navigation', ModifierScheme::altShift(), [
        new ShortcutBinding(
            new ShortcutTarget('navigation', 'products'),
            KeyCombo::parse('alt+shift+p'),
        ),
    ]);
}

function tableGroup(): ResolvedGroup
{
    return new ResolvedGroup('table', ModifierScheme::none(), [
        new ShortcutBinding(
            new ShortcutTarget('table', 'search'),
            KeyCombo::parse('/'),
        ),
    ]);
}

it('exposes its groups in the order given', function () {
    $map = new ResolvedMap([navGroup(), tableGroup()]);

    expect($map->groups())->toHaveCount(2)
        ->and($map->groups()[0]->setKey)->toBe('navigation')
        ->and($map->groups()[1]->setKey)->toBe('table');
});

it('serializes to a client array grouped by set with a shared modifier', function () {
    $map = new ResolvedMap([navGroup()]);

    expect($map->toArray())->toBe([
        [
            'set' => 'navigation',
            'modifier' => 'alt+shift',
            'bindings' => [
                [
                    'target' => 'navigation:products',
                    'code' => 'KeyP',
                    'enabled' => true,
                    'source' => 'convention',
                ],
            ],
        ],
    ]);
});

it('serializes bare table keys with an empty modifier', function () {
    $map = new ResolvedMap([tableGroup()]);

    $group = $map->toArray()[0];

    expect($group['set'])->toBe('table')
        ->and($group['modifier'])->toBe('')
        ->and($group['bindings'][0]['code'])->toBe('Slash');
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
