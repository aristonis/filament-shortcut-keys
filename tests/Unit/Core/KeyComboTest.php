<?php

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Exceptions\InvalidKeyComboException;

it('parses modifiers and maps the letter to a physical key code', function () {
    $combo = KeyCombo::parse('alt+shift+p');

    expect($combo->alt)->toBeTrue()
        ->and($combo->shift)->toBeTrue()
        ->and($combo->ctrl)->toBeFalse()
        ->and($combo->meta)->toBeFalse()
        ->and($combo->code)->toBe('KeyP');
});

it('maps the slash key to its physical code', function () {
    expect(KeyCombo::parse('alt+/')->code)->toBe('Slash');
});

it('accepts a bare key with no modifiers (table keys, letter actions like c/e)', function () {
    $combo = KeyCombo::parse('c');

    expect($combo->code)->toBe('KeyC')
        ->and($combo->ctrl)->toBeFalse()
        ->and($combo->alt)->toBeFalse()
        ->and($combo->shift)->toBeFalse()
        ->and($combo->meta)->toBeFalse();
});

it('is case-insensitive and order-independent when comparing', function () {
    expect(KeyCombo::parse('ALT+P')->equals(KeyCombo::parse('p+alt')))->toBeTrue();
});

it('distinguishes combos that differ by a modifier', function () {
    expect(KeyCombo::parse('alt+p')->equals(KeyCombo::parse('alt+shift+p')))->toBeFalse();
});

it('renders a canonical string (fixed modifier order, human key token)', function () {
    expect(KeyCombo::parse('p+shift+alt')->toString())->toBe('alt+shift+p');
});

it('throws a domain exception on a modifier-only combo', function () {
    KeyCombo::parse('alt+');
})->throws(InvalidKeyComboException::class);

it('throws a domain exception on an empty combo', function () {
    KeyCombo::parse('   ');
})->throws(InvalidKeyComboException::class);
