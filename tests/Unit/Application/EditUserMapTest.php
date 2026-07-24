<?php

use Aristonis\FilamentShortcutKeys\Application\EditUserMap;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidCustomBindingException;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryMapEditor;

function personalEditor(InMemoryMapEditor $editor): EditUserMap
{
    return new EditUserMap($editor, 'personal');
}

it('remaps a shortcut to a letter', function () {
    $editor = new InMemoryMapEditor(mapId: 7);

    $id = personalEditor($editor)->remap('App\\User', '1', 'admin', 'navigation:orders', 'o');

    expect($id)->toBe(7)
        ->and($editor->saved)->toHaveCount(1);

    $edit = $editor->saved[0]['edit'];
    expect($edit->target)->toBe('navigation:orders')
        ->and($edit->letter)->toBe('o')
        ->and($edit->disabled)->toBeFalse()
        ->and($edit->payload)->toBeNull();
});

it('disables a shortcut', function () {
    $editor = new InMemoryMapEditor;

    personalEditor($editor)->disable('App\\User', '1', 'admin', 'global:export');

    $edit = $editor->saved[0]['edit'];
    expect($edit->disabled)->toBeTrue()
        ->and($edit->letter)->toBeNull();
});

it('enables a shortcut by dropping its override', function () {
    $editor = new InMemoryMapEditor;

    personalEditor($editor)->enable('App\\User', '1', 'admin', 'global:export');

    expect($editor->saved)->toBeEmpty()
        ->and($editor->removed)->toHaveCount(1)
        ->and($editor->removed[0]['target'])->toBe('global:export');
});

it('adds a custom binding pointing at a selector', function () {
    $editor = new InMemoryMapEditor;

    personalEditor($editor)->addCustomBinding('App\\User', '1', 'admin', 'custom:deploy', 'd', ['selector' => '#deploy']);

    $edit = $editor->saved[0]['edit'];
    expect($edit->payload)->toBe(['selector' => '#deploy'])
        ->and($edit->letter)->toBe('d');
});

it('adds a custom binding pointing at a route', function () {
    $editor = new InMemoryMapEditor;

    personalEditor($editor)->addCustomBinding('App\\User', '1', 'admin', 'custom:reports', 'r', ['route' => '/admin/reports']);

    expect($editor->saved[0]['edit']->payload)->toBe(['route' => '/admin/reports']);
});

it('rejects a custom binding whose payload is not exactly one of selector or route', function (array $payload) {
    $editor = new InMemoryMapEditor;

    expect(fn () => personalEditor($editor)->addCustomBinding('App\\User', '1', 'admin', 'custom:x', 'x', $payload))
        ->toThrow(InvalidCustomBindingException::class);

    expect($editor->saved)->toBeEmpty();
})->with([
    'both keys' => [['selector' => '#a', 'route' => '/b']],
    'unknown key' => [['action' => 'doThing']],
    'empty selector' => [['selector' => '  ']],
    'non-string route' => [['route' => 123]],
    'empty payload' => [[]],
    'javascript route' => [['route' => 'javascript:alert(1)']],
    'external route' => [['route' => 'https://evil.example/phish']],
    'protocol-relative route' => [['route' => '//evil.example']],
]);

it('rejects a custom binding whose target is not namespaced custom:', function () {
    $editor = new InMemoryMapEditor;

    expect(fn () => personalEditor($editor)->addCustomBinding('App\\User', '1', 'admin', 'global:export', 'x', ['selector' => '#x']))
        ->toThrow(InvalidCustomBindingException::class);

    expect($editor->saved)->toBeEmpty();
});

it('blocks every edit in locked mode', function () {
    $editor = new InMemoryMapEditor;
    $locked = new EditUserMap($editor, 'locked');

    expect(fn () => $locked->remap('App\\User', '1', 'admin', 'navigation:orders', 'o'))
        ->toThrow(EditingLockedException::class);
    expect(fn () => $locked->disable('App\\User', '1', 'admin', 'global:export'))
        ->toThrow(EditingLockedException::class);
    expect(fn () => $locked->enable('App\\User', '1', 'admin', 'global:export'))
        ->toThrow(EditingLockedException::class);
    expect(fn () => $locked->addCustomBinding('App\\User', '1', 'admin', 'custom:x', 'x', ['route' => '/x']))
        ->toThrow(EditingLockedException::class);

    expect($editor->saved)->toBeEmpty()
        ->and($editor->removed)->toBeEmpty();
});
