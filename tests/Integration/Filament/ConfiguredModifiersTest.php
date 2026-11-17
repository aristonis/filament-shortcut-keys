<?php

use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;
use Aristonis\FilamentShortcutKeys\Filament\Pages\ShortcutReference;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;

/** Renders a panel page and returns the innermost throwable, since Blade wraps what a render hook throws. */
function renderThrowable(): ?Throwable
{
    try {
        test()->withoutExceptionHandling()->get('/admin/orders');
    } catch (Throwable $thrown) {
        while ($thrown->getPrevious() !== null) {
            $thrown = $thrown->getPrevious();
        }

        return $thrown;
    }

    return null;
}

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

    $navigation = collect(injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent()))
        ->firstWhere('set', 'navigation');

    expect($modifiers['navigation'])->toBe('')
        ->and($navigation['leader'])->toBe('KeyG')
        ->and($modifiers['global'])->toBe('')
        ->and($modifiers['table'])->toBe('');
});

it('applies a configured modifier scheme to a set', function () {
    config()->set('shortcut-keys.modifiers.navigation', ['ctrl', 'alt']);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('ctrl+alt')
        ->and($modifiers['global'])->toBe('');
});

it('moves the global set onto a new modifier without disturbing the others', function () {
    config()->set('shortcut-keys.modifiers.global', ['ctrl', 'alt']);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['global'])->toBe('ctrl+alt')
        ->and($modifiers['navigation'])->toBe('')
        ->and($modifiers['table'])->toBe('');
});

it('keeps a set on its built-in scheme when config does not mention it', function () {
    config()->set('shortcut-keys.modifiers', ['navigation' => ['ctrl']]);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('ctrl')
        ->and($modifiers['global'])->toBe('');
});

it('reads an empty configured list as bare keys', function () {
    // Header and row actions are bare by default; they move off so no lettered set shares the pool.
    config()->set('shortcut-keys.modifiers.navigation', []);
    config()->set('shortcut-keys.modifiers.global', ['alt']);
    config()->set('shortcut-keys.modifiers.row-action', ['alt', 'shift']);

    $modifiers = injectedModifiers((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($modifiers['navigation'])->toBe('');
});

it('refuses a page set on the same keys as navigation, since the two are lettered apart', function () {
    // Navigation and header actions are resolved separately, so sharing alt could give both one letter.
    config()->set('shortcut-keys.modifiers.navigation', ['alt']);
    config()->set('shortcut-keys.modifiers.global', ['alt']);

    expect(renderThrowable())->toBeInstanceOf(InvalidModifierSchemeException::class)
        ->getMessage()->toContain('global');
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

it('refuses a leader on a key the table already uses', function () {
    // j would start a wait instead of moving the row cursor.
    config()->set('shortcut-keys.modifiers.navigation', ['j']);

    expect(renderThrowable())->toBeInstanceOf(InvalidModifierSchemeException::class);
});

it('refuses a page set sharing the navigation leader, since the two are lettered apart', function () {
    config()->set('shortcut-keys.modifiers.global', ['g']);

    expect(renderThrowable())->toBeInstanceOf(InvalidModifierSchemeException::class);
});

it('accepts a page set on a leader of its own', function () {
    config()->set('shortcut-keys.modifiers.global', ['a']);

    $global = collect(injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent()))
        ->firstWhere('set', 'global');

    expect($global['leader'])->toBe('KeyA');
});

it('keeps a row action off a letter the overlay pinned to a header action', function () {
    config()->set('shortcut-keys.overlay', ['global:export' => ['letter' => 'a']]);

    $rowActions = collect(injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent()))
        ->firstWhere('set', 'row-action');
    $approve = collect($rowActions['bindings'])->firstWhere('target', 'row-action:approve');

    expect($approve)->not->toBeNull()
        ->and($approve['code'])->not->toBe('KeyA');
});

it('keys an instant header action only once a developer pins it', function () {
    $targets = fn () => array_column(
        collect(injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent()))->firstWhere('set', 'global')['bindings'],
        'target',
    );

    expect($targets())->not->toContain('global:ping');

    config()->set('shortcut-keys.overlay', ['global:ping' => ['letter' => 'p']]);

    expect($targets())->toContain('global:ping');
});

it('does not hold back a letter whose overlay entry is disabled', function () {
    config()->set('shortcut-keys.overlay', ['global:export' => ['letter' => 'a', 'disabled' => true]]);

    $rowActions = collect(injectedKeymap((string) $this->get('/admin/orders')->assertOk()->getContent()))
        ->firstWhere('set', 'row-action');

    expect(collect($rowActions['bindings'])->firstWhere('target', 'row-action:approve')['code'])->toBe('KeyA');
});

it('refuses bare navigation while row actions are bare too', function () {
    config()->set('shortcut-keys.modifiers.navigation', []);
    config()->set('shortcut-keys.modifiers.global', ['alt']);

    expect(renderThrowable())->toBeInstanceOf(InvalidModifierSchemeException::class)
        ->getMessage()->toContain('row-action');
});

it('refuses header actions on the keys custom bindings use, not only navigation\'s', function () {
    config()->set('shortcut-keys.modifiers.custom', ['alt']);
    config()->set('shortcut-keys.modifiers.global', ['alt']);

    expect(renderThrowable())->toBeInstanceOf(InvalidModifierSchemeException::class);
});
