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
        held('alt', 'shift'),
        [binding('products', 'Products')],
    );

    expect($result[0]->keyCombo)->toEqual(combo('alt+shift+p'));
});

it('falls through to the next free letter of the hint on a clash', function () {
    // Products -> P; Payments wants P (taken) -> next hint char 'a'
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [binding('products', 'Products'), binding('payments', 'Payments')],
    );

    expect($result[0]->keyCombo)->toEqual(combo('alt+shift+p'))
        ->and($result[1]->keyCombo)->toEqual(combo('alt+shift+a'));
});

it('assigns greedily in input order', function () {
    // Orders -> O; Overview wants O (taken) -> 'v'
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [binding('orders', 'Orders'), binding('overview', 'Overview')],
    );

    expect($result[0]->keyCombo)->toEqual(combo('alt+shift+o'))
        ->and($result[1]->keyCombo)->toEqual(combo('alt+shift+v'));
});

it('honors an already-assigned (forced) combo and reserves its letter for others', function () {
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [
            binding('products', 'Products', combo('alt+shift+p')),  // forced
            binding('payments', 'Payments'),
        ],
    );

    expect($result[0]->keyCombo)->toEqual(combo('alt+shift+p'))   // untouched
        ->and($result[1]->keyCombo)->toEqual(combo('alt+shift+a')); // avoided P
});

it('builds the combo from the pool modifier (global Alt, not Alt+Shift)', function () {
    $result = (new LetterAssigner)->assign(
        held('alt'),
        [binding('export', 'Export')],
    );

    expect($result[0]->keyCombo)->toEqual(combo('alt+e'));
});

it('skips every taken hint char and lands on the first free one', function () {
    // B and A forced taken; hint "Bat" -> b(taken), a(taken), t(free) -> t
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [
            binding('b-thing', 'B', combo('alt+shift+b')),
            binding('a-thing', 'A', combo('alt+shift+a')),
            binding('bat', 'Bat'),
        ],
    );

    expect($result[2]->keyCombo)->toEqual(combo('alt+shift+t'));
});

it('falls back to the alphabet when every hint letter is taken', function () {
    // C, A, T all forced-taken; hint "Cat" is exhausted -> first free alphabet letter is 'b'
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [
            binding('c-thing', 'C', combo('alt+shift+c')),
            binding('a-thing', 'A', combo('alt+shift+a')),
            binding('t-thing', 'T', combo('alt+shift+t')),
            binding('cat', 'Cat'),
        ],
    );

    expect($result[3]->keyCombo)->toEqual(combo('alt+shift+b'));
});

it('preserves target, source and hint on the returned binding', function () {
    $result = (new LetterAssigner)->assign(
        held('alt', 'shift'),
        [binding('products', 'Products')],
    );

    expect($result[0]->target->identity())->toBe('navigation:products')
        ->and($result[0]->letterHint)->toBe('Products');
});

it('drops bindings once the 26 letters are exhausted', function () {
    $bindings = [];
    for ($i = 0; $i < 27; $i++) {
        $bindings[] = binding("item{$i}", 'Item');
    }

    $result = (new LetterAssigner)->assign(held('alt', 'shift'), $bindings);

    expect($result)->toHaveCount(26);
    foreach ($result as $binding) {
        expect($binding->keyCombo)->not->toBeNull();
    }
});

it('drops a later binding whose forced key duplicates an earlier one', function () {
    $result = (new LetterAssigner)->assign(held('alt', 'shift'), [
        binding('products', 'Products', combo('alt+shift+r')),
        binding('reports', 'Reports', combo('alt+shift+r')),
    ]);

    expect($result)->toHaveCount(1)
        ->and($result[0]->target->structureKey)->toBe('products');
});

it('assigns from the alphabet when the hint is null', function () {
    $binding = new ShortcutBinding(new ShortcutTarget('navigation', 'x'), null, letterHint: null);

    $result = (new LetterAssigner)->assign(held('alt', 'shift'), [$binding]);

    expect($result[0]->keyCombo)->toEqual(combo('alt+shift+a'));
});

it('routes around keys another set already owns in the pool', function () {
    $result = (new LetterAssigner)->assign(
        ModifierScheme::none(),
        [binding('join', 'join')],
        ['KeyJ', 'KeyO'],
    );

    expect($result[0]->keyCombo->code)->toBe('KeyI');
});
