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
        ->and($set->defaultModifier())->toEqual(ModifierScheme::none())
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

it('never gives a row action a key reserved for its pool', function () {
    // "join" and "keep" would otherwise take j and k, and the cursor keys would stop working.
    $set = new RowActionSet(['join', 'keep'], unavailableCodes: ['KeyJ', 'KeyK', 'KeyG']);

    $codes = collect($set->discover(emptyNav(), rowActionPage(['join', 'keep']), 'admin'))
        ->map(fn ($b) => $b->keyCombo->code)
        ->all();

    expect($codes)->toHaveCount(2)
        ->not->toContain('KeyJ')
        ->not->toContain('KeyK');
});

it('keeps every registered name, on its usual letter, when a nested table hides the actions', function () {
    $set = new RowActionSet(['approve', 'archive']);
    $page = new InMemoryPageContextProvider([], true, [], hasNestedTables: true);

    $bindings = collect($set->discover(emptyNav(), $page, 'admin'))
        ->mapWithKeys(fn ($b) => [$b->target->structureKey => $b->keyCombo->code]);

    expect($bindings->all())->toBe(['approve' => 'KeyA', 'archive' => 'KeyR']);
});
