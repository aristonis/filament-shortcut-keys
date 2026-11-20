<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidModifierSchemeException;

it('holds the four modifier flags', function () {
    $scheme = new ModifierScheme(ctrl: false, alt: true, shift: true, meta: false);

    expect($scheme->ctrl)->toBeFalse()
        ->and($scheme->alt)->toBeTrue()
        ->and($scheme->shift)->toBeTrue()
        ->and($scheme->meta)->toBeFalse();
});

it('builds the bare scheme (table default, no modifiers) via a named constructor', function () {
    $scheme = ModifierScheme::none();

    expect($scheme->ctrl)->toBeFalse()
        ->and($scheme->alt)->toBeFalse()
        ->and($scheme->shift)->toBeFalse()
        ->and($scheme->meta)->toBeFalse();
});

it('renders a canonical string in fixed modifier order', function () {
    expect(held('alt', 'shift')->toString())->toBe('alt+shift')
        ->and(held('alt')->toString())->toBe('alt')
        ->and(ModifierScheme::none()->toString())->toBe('');
});

it('builds a scheme from configured modifier tokens', function () {
    $scheme = ModifierScheme::fromTokens(['alt', 'shift']);

    expect($scheme)->toEqual(new ModifierScheme(ctrl: false, alt: true, shift: true, meta: false));
});

it('reads an empty token list as bare keys', function () {
    expect(ModifierScheme::fromTokens([]))->toEqual(ModifierScheme::none());
});

it('ignores the order the tokens are written in', function () {
    expect(ModifierScheme::fromTokens(['shift', 'alt']))->toEqual(ModifierScheme::fromTokens(['alt', 'shift']));
});

it('rejects a token it cannot map to a real modifier', function () {
    // "mod" resolves differently per platform, so it cannot be decided on the server.
    ModifierScheme::fromTokens(['alt', 'mod']);
})->throws(InvalidModifierSchemeException::class);

it('reads a single letter token as a leader key pressed before the letter', function () {
    $scheme = ModifierScheme::fromTokens(['g']);

    expect($scheme->leader)->toBe('KeyG')
        ->and($scheme)->toEqual(ModifierScheme::leader('g'))
        ->and($scheme)->not->toEqual(ModifierScheme::none());
});

it('names a leader scheme apart from bare keys, but holds no modifier', function () {
    $scheme = ModifierScheme::leader('g');

    expect($scheme->toString())->toBe('leader:g')
        ->and($scheme->heldModifiers())->toBe('')
        ->and(held('alt', 'shift')->heldModifiers())->toBe('alt+shift');
});

it('rejects a leader key mixed with held modifiers', function () {
    ModifierScheme::fromTokens(['alt', 'g']);
})->throws(InvalidModifierSchemeException::class);

it('rejects a leader token that is not one letter', function () {
    ModifierScheme::fromTokens(['5']);
})->throws(InvalidModifierSchemeException::class);
