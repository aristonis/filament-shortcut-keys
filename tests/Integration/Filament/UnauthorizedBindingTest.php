<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ManageShortcuts;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;

/**
 * A custom binding can only ask the browser to do one of two things: click something already on the
 * page, or visit a route. Neither grants access, which is the whole security model — so "binding a key
 * to something you are not authorized for has no effect" is really two claims, checked here one each.
 *
 * The keymap deliberately still contains the binding in both cases. It is public by design, and
 * filtering it would imply a guarantee that is enforced somewhere else entirely.
 */

/** @return array{0: string, 1: array<string, mixed>} the injected custom group and its single binding */
function customBinding(string $html): array
{
    $group = collect(injectedKeymap($html))->firstWhere('set', 'custom');

    expect($group)->not->toBeNull('no custom group was injected');
    expect($group['bindings'])->toHaveCount(1);

    return [$group['handler'], $group['bindings'][0]];
}

function bindOnDefaultMap(string $target, array $payload): void
{
    $map = ShortcutMap::query()->create([
        'panel_id' => 'admin',
        'type' => MapType::SYSTEM,
        'default_marker' => 'admin',
        'version' => 1,
    ]);

    ShortcutMapEntry::query()->create([
        'map_id' => $map->id,
        'target' => $target,
        'letter' => 'j',
        'disabled' => false,
        'payload' => $payload,
    ]);
}

it('sends a route binding to a page the server refuses', function () {
    // The manager is closed to this visitor: not personal mode, and the authoring gate denies.
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', null);

    $refused = parse_url(ManageShortcuts::getUrl(), PHP_URL_PATH);
    bindOnDefaultMap('custom:manager', ['route' => $refused]);

    [$handler, $binding] = customBinding((string) $this->get('/admin/orders')->assertOk()->getContent());

    // The key is live and points where the admin asked, so pressing it really does navigate.
    expect($handler)->toBe('custom')
        ->and($binding['activation'])->toBe(['kind' => 'navigate', 'url' => $refused]);

    // And the destination refuses them, which is where the binding stops being useful.
    $this->get($refused)->assertForbidden();
});

it('sends a selector binding at a control that was never rendered', function () {
    // An action this page does not expose, standing in for one the admin may not use: Filament does
    // not render a control the viewer cannot trigger, so the selector matches nothing either way.
    $selector = '[wire\\:click^="mountAction(\'destroyEverything\'"]';
    bindOnDefaultMap('custom:destroy', ['selector' => $selector]);

    $html = (string) $this->get('/admin/orders')->assertOk()->getContent();

    [$handler, $binding] = customBinding($html);

    expect($handler)->toBe('custom')
        ->and($binding['activation'])->toBe(['kind' => 'click', 'selector' => $selector]);

    // Nothing on the page answers to it, so the click lands on nothing.
    expect($html)->not->toContain("mountAction('destroyEverything'");
});

it('keeps an unusable binding in the keymap rather than filtering it out', function () {
    config()->set('shortcut-keys.customization', 'locked');
    config()->set('shortcut-keys.authorize', null);

    bindOnDefaultMap('custom:manager', ['route' => parse_url(ManageShortcuts::getUrl(), PHP_URL_PATH)]);

    [, $binding] = customBinding((string) $this->get('/admin/orders')->assertOk()->getContent());

    // Worth pinning: the map is built once for everyone and cached on the map's identity, so a
    // per-viewer filter here would silently make that cache key wrong for whoever it was built for.
    expect($binding['target'])->toBe('custom:manager');
});
