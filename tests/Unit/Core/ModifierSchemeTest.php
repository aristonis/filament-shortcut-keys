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

it('builds the Alt+Shift scheme (navigation default) via a named constructor', function () {
    $scheme = ModifierScheme::altShift();

    expect($scheme->alt)->toBeTrue()
        ->and($scheme->shift)->toBeTrue()
        ->and($scheme->ctrl)->toBeFalse()
        ->and($scheme->meta)->toBeFalse();
});

it('builds the Alt scheme (global default) via a named constructor', function () {
    $scheme = ModifierScheme::alt();

    expect($scheme->alt)->toBeTrue()
        ->and($scheme->shift)->toBeFalse()
        ->and($scheme->ctrl)->toBeFalse()
        ->and($scheme->meta)->toBeFalse();
});

it('builds the bare scheme (table default, no modifiers) via a named constructor', function () {
    $scheme = ModifierScheme::none();

    expect($scheme->ctrl)->toBeFalse()
        ->and($scheme->alt)->toBeFalse()
        ->and($scheme->shift)->toBeFalse()
        ->and($scheme->meta)->toBeFalse();
});

it('is equal when every flag matches and differs otherwise', function () {
    expect(ModifierScheme::altShift()->equals(ModifierScheme::altShift()))->toBeTrue()
        ->and(ModifierScheme::altShift()->equals(ModifierScheme::alt()))->toBeFalse();
});

it('renders a canonical string in fixed modifier order', function () {
    expect(ModifierScheme::altShift()->toString())->toBe('alt+shift')
        ->and(ModifierScheme::alt()->toString())->toBe('alt')
        ->and(ModifierScheme::none()->toString())->toBe('');
});

it('builds a scheme from configured modifier tokens', function () {
    $scheme = ModifierScheme::fromTokens(['alt', 'shift']);

    expect($scheme->equals(ModifierScheme::altShift()))->toBeTrue();
});

it('reads an empty token list as bare keys', function () {
    expect(ModifierScheme::fromTokens([])->equals(ModifierScheme::none()))->toBeTrue();
});

it('ignores the order the tokens are written in', function () {
    expect(ModifierScheme::fromTokens(['shift', 'alt'])->equals(ModifierScheme::altShift()))->toBeTrue();
});

it('rejects a token it cannot map to a real modifier', function () {
    // "mod" resolves differently per platform, so it cannot be decided on the server.
    ModifierScheme::fromTokens(['alt', 'mod']);
})->throws(InvalidModifierSchemeException::class);
