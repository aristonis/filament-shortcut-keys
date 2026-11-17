<?php

use Aristonis\FilamentShortcutKeys\Core\Sets\RowActionSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

function rowActionPage(array $rowActionNames): InMemoryPageContextProvider
{
    $rowActions = array_map(
        fn (string $name) => new ActionTarget($name, ucfirst($name), "selector-$name"),
        $rowActionNames,
    );

    return new InMemoryPageContextProvider(actions: [], hasTable: true, rowActions: $rowActions);
}

function emptyNav(): InMemoryNavigationProvider
{
    return new InMemoryNavigationProvider(items: [], versionToken: 'v');
}

it('is keyed "row-action", bare modifier, and shares the table client handler', function () {
    $set = new RowActionSet(['approve', 'reject']);

    expect($set->key())->toBe('row-action')
        ->and($set->defaultModifier()->equals(ModifierScheme::none()))->toBeTrue()
        ->and($set->clientHandler())->toBe('table');
});

it('emits a binding only for names both registered AND present on the page', function () {
    $set = new RowActionSet(['approve', 'reject']);

    // page has approve + a built-in view; reject is registered but not on this page.
    $bindings = $set->discover(emptyNav(), rowActionPage(['approve', 'view']), 'admin');

    expect($bindings)->toHaveCount(1)
        ->and($bindings[0]->target->set)->toBe('row-action')
        ->and($bindings[0]->target->structureKey)->toBe('approve')
        ->and($bindings[0]->keyCombo)->not->toBeNull();
});

it('excludes the table-reserved names (view/edit/delete) even when registered', function () {
    $set = new RowActionSet(['edit', 'approve']);

    $bindings = $set->discover(emptyNav(), rowActionPage(['edit', 'approve']), 'admin');

    $keys = array_map(fn ($b) => $b->target->structureKey, $bindings);

    expect($keys)->toContain('approve')->not->toContain('edit');
});

it('assigns letters over the FULL registered list so a letter is stable when others are absent', function () {
    // approve + archive both start with "a". Over the full list: approve -> KeyA, archive -> KeyR.
    $set = new RowActionSet(['approve', 'archive']);

    // Only archive is present on this page. Its letter must still be KeyR (computed over the full
    // list where approve reserved KeyA), NOT KeyA — otherwise the key would jump between pages.
    $bindings = $set->discover(emptyNav(), rowActionPage(['archive']), 'admin');

    $archive = collect($bindings)->firstWhere(fn ($b) => $b->target->structureKey === 'archive');

    expect($archive->keyCombo->code)->toBe('KeyR');
});

it('never gives a row action the letters that move the row cursor', function () {
    // "join" and "keep" would otherwise take j and k, and the cursor keys would stop working.
    $set = new RowActionSet(['join', 'keep']);

    $codes = collect($set->discover(emptyNav(), rowActionPage(['join', 'keep']), 'admin'))
        ->map(fn ($b) => $b->keyCombo->code)
        ->all();

    expect($codes)->toHaveCount(2)
        ->not->toContain('KeyJ')
        ->not->toContain('KeyK');
});

it('leaves j and k to row actions when they sit on a different modifier than the table', function () {
    // Alt+J and a bare J never meet, so reserving j there would only shift letters for nothing.
    $set = new RowActionSet(['join'], ModifierScheme::alt(), tableModifier: ModifierScheme::none());

    $bindings = $set->discover(emptyNav(), rowActionPage(['join']), 'admin');

    expect($bindings[0]->keyCombo->code)->toBe('KeyJ');
});
