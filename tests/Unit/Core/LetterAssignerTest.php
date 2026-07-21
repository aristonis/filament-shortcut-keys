<?php

use Aristonis\FilamentShortcutKeys\Core\Resolution\LetterAssigner;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

function binding(string $structureKey, string $hint, ?KeyCombo $combo = null): ShortcutBinding
{
    return new ShortcutBinding(
        new ShortcutTarget('navigation', $structureKey),
        $combo,
        letterHint: $hint,
    );
}

it('assigns the first letter of the hint, built from the given modifier scheme', function () {
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [binding('products', 'Products')],
    );

    expect($result[0]->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue();
});

it('falls through to the next free letter of the hint on a clash (AC-2)', function () {
    // Products -> P; Payments wants P (taken) -> next hint char 'a'
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [binding('products', 'Products'), binding('payments', 'Payments')],
    );

    expect($result[0]->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue()
        ->and($result[1]->keyCombo->equals(KeyCombo::parse('alt+shift+a')))->toBeTrue();
});

it('assigns greedily in input order', function () {
    // Orders -> O; Overview wants O (taken) -> 'v'
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [binding('orders', 'Orders'), binding('overview', 'Overview')],
    );

    expect($result[0]->keyCombo->equals(KeyCombo::parse('alt+shift+o')))->toBeTrue()
        ->and($result[1]->keyCombo->equals(KeyCombo::parse('alt+shift+v')))->toBeTrue();
});

it('honors an already-assigned (forced) combo and reserves its letter for others', function () {
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [
            binding('products', 'Products', KeyCombo::parse('alt+shift+p')),  // forced
            binding('payments', 'Payments'),
        ],
    );

    expect($result[0]->keyCombo->equals(KeyCombo::parse('alt+shift+p')))->toBeTrue()   // untouched
        ->and($result[1]->keyCombo->equals(KeyCombo::parse('alt+shift+a')))->toBeTrue(); // avoided P
});

it('builds the combo from the pool modifier (global Alt, not Alt+Shift)', function () {
    $result = (new LetterAssigner)->assign(
        ModifierScheme::alt(),
        [binding('export', 'Export')],
    );

    expect($result[0]->keyCombo->equals(KeyCombo::parse('alt+e')))->toBeTrue();
});

it('skips every taken hint char and lands on the first free one', function () {
    // B and A forced taken; hint "Bat" -> b(taken), a(taken), t(free) -> t
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [
            binding('b-thing', 'B', KeyCombo::parse('alt+shift+b')),
            binding('a-thing', 'A', KeyCombo::parse('alt+shift+a')),
            binding('bat', 'Bat'),
        ],
    );

    expect($result[2]->keyCombo->equals(KeyCombo::parse('alt+shift+t')))->toBeTrue();
});

it('falls back to the alphabet when every hint letter is taken', function () {
    // C, A, T all forced-taken; hint "Cat" is exhausted -> first free alphabet letter is 'b'
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [
            binding('c-thing', 'C', KeyCombo::parse('alt+shift+c')),
            binding('a-thing', 'A', KeyCombo::parse('alt+shift+a')),
            binding('t-thing', 'T', KeyCombo::parse('alt+shift+t')),
            binding('cat', 'Cat'),
        ],
    );

    expect($result[3]->keyCombo->equals(KeyCombo::parse('alt+shift+b')))->toBeTrue();
});

it('preserves target, source and hint on the returned binding', function () {
    $result = (new LetterAssigner)->assign(
        ModifierScheme::altShift(),
        [binding('products', 'Products')],
    );

    expect($result[0]->target->identity())->toBe('navigation:products')
        ->and($result[0]->letterHint)->toBe('Products');
});
