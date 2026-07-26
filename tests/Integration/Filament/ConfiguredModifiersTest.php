<?php

use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;

/** @return array<string, string> set key => the modifier string the client was told to match */
function injectedModifiers(string $html): array
{
    $modifiers = [];

    foreach (injectedKeymap($html) ?? [] as $group) {
        $modifiers[$group['set']] = $group['modifier'];
    }

    return $modifiers;
}

it('serves the built-in schemes when nothing is configured', function () {
    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('alt+shift')
        ->and($modifiers['global'])->toBe('alt')
        ->and($modifiers['table'])->toBe('');
});

it('applies a configured modifier scheme to a set', function () {
    config()->set('shortcut-keys.modifiers.navigation', ['ctrl', 'alt']);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('ctrl+alt')
        ->and($modifiers['global'])->toBe('alt');
});

it('keeps a set on its built-in scheme when config does not mention it', function () {
    config()->set('shortcut-keys.modifiers', ['navigation' => ['ctrl']]);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('ctrl')
        ->and($modifiers['global'])->toBe('alt');
});

it('reads an empty configured list as bare keys', function () {
    config()->set('shortcut-keys.modifiers.navigation', []);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('');
});

it('re-letters a set that moved onto another set\'s scheme so the two cannot collide', function () {
    // Global actions already own alt; moving navigation there puts both in one letter pool.
    config()->set('shortcut-keys.modifiers.navigation', ['alt']);

    $keymap = injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent());

    $codes = [];
    foreach ($keymap as $group) {
        if (in_array($group['set'], ['navigation', 'global'], true)) {
            $codes = [...$codes, ...array_column($group['bindings'], 'code')];
        }
    }

    expect($codes)->not->toBeEmpty()
        ->and(array_unique($codes))->toHaveCount(count($codes));
});

it('fails loudly on a modifier token it cannot resolve', function () {
    config()->set('shortcut-keys.modifiers.navigation', ['mod']);

    try {
        $this->withoutExceptionHandling()->get('/admin/orders');
    } catch (Throwable $thrown) {
        // The keymap is built inside a render hook, so Blade wraps whatever it throws.
        while ($thrown->getPrevious() !== null) {
            $thrown = $thrown->getPrevious();
        }

        expect($thrown)->toBeInstanceOf(InvalidModifierSchemeException::class);

        return;
    }

    $this->fail('an unresolvable modifier token was accepted instead of throwing');
});

it('keeps the reference page and the injected keymap on the same scheme', function () {
    config()->set('shortcut-keys.modifiers.navigation', ['ctrl', 'shift']);

    $html = (string) $this->get(ShortcutReference::getUrl())->assertOk()->getContent();

    // The reference renders each combo as separate key tokens, so a scheme change shows up there too.
    expect($html)->toContain('Ctrl')
        ->and($html)->toContain(OrderResource::getNavigationLabel());
});
