<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\BindingSource;
use Aristonis\FilamentShortcutKeys\Core\Sets\GlobalSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\NavigationSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\PageSet;
use Aristonis\FilamentShortcutKeys\Core\Sets\TableSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryNavigationProvider;
use Aristonis\FilamentShortcutKeys\Tests\Fakes\InMemoryPageContextProvider;

function nav(array $items): InMemoryNavigationProvider
{
    return new InMemoryNavigationProvider(items: $items, versionToken: 'v1');
}

function page(array $actions = [], bool $hasTable = false): InMemoryPageContextProvider
{
    return new InMemoryPageContextProvider(actions: $actions, hasTable: $hasTable);
}

// --- Navigation set -------------------------------------------------------

it('the navigation set is keyed "navigation" and defaults to Alt+Shift', function () {
    $set = new NavigationSet;

    expect($set->key())->toBe('navigation')
        ->and($set->defaultModifier()->equals(ModifierScheme::altShift()))->toBeTrue();
});

it('the navigation set discovers one convention binding per sidebar item, letter unassigned', function () {
    $bindings = (new NavigationSet)->discover(
        nav([
            new NavItem('App\\Filament\\Resources\\ProductResource', 'Products', '/admin/products'),
            new NavItem('App\\Filament\\Resources\\OrderResource', 'Orders', '/admin/orders'),
        ]),
        page(),
        'admin',
    );

    expect($bindings)->toHaveCount(2)
        ->and($bindings[0]->target->set)->toBe('navigation')
        ->and($bindings[0]->target->structureKey)->toBe('App\\Filament\\Resources\\ProductResource')
        ->and($bindings[0]->keyCombo)->toBeNull()
        ->and($bindings[0]->letterHint)->toBe('Products')   // label drives the letter
        ->and($bindings[0]->source)->toBe(BindingSource::CONVENTION);
});

// --- Global set -----------------------------------------------------------

it('the global set is keyed "global" and defaults to Alt', function () {
    $set = new GlobalSet;

    expect($set->key())->toBe('global')
        ->and($set->defaultModifier()->equals(ModifierScheme::alt()))->toBeTrue();
});

it('the global set discovers one convention binding per page action, letter unassigned', function () {
    $bindings = (new GlobalSet)->discover(
        nav([]),
        page(actions: [new ActionTarget('create', 'Create', '[wire\\:key="create"]')]),
        'admin',
    );

    expect($bindings)->toHaveCount(1)
        ->and($bindings[0]->target->set)->toBe('global')
        ->and($bindings[0]->target->structureKey)->toBe('create')
        ->and($bindings[0]->keyCombo)->toBeNull()
        ->and($bindings[0]->letterHint)->toBe('create')   // action name drives the letter
        ->and($bindings[0]->source)->toBe(BindingSource::CONVENTION);
});

// --- Table set ------------------------------------------------------------

it('the table set is keyed "table" and defaults to bare keys (no modifier)', function () {
    $set = new TableSet;

    expect($set->key())->toBe('table')
        ->and($set->defaultModifier()->equals(ModifierScheme::none()))->toBeTrue();
});

it('the table set discovers nothing when no table is present', function () {
    expect((new TableSet)->discover(nav([]), page(hasTable: false), 'admin'))->toBe([]);
});

it('the table set discovers fixed reserved bindings when a table is present', function () {
    $bindings = (new TableSet)->discover(nav([]), page(hasTable: true), 'admin');

    expect($bindings)->not->toBeEmpty();

    foreach ($bindings as $binding) {
        expect($binding->target->set)->toBe('table')
            ->and($binding->keyCombo)->not->toBeNull()   // reserved keys are fixed, not auto-assigned
            ->and($binding->source)->toBe(BindingSource::CONVENTION);
    }

    $search = collect($bindings)->first(fn (ShortcutBinding $b) => $b->target->structureKey === 'search');

    expect($search)->not->toBeNull()
        ->and($search->keyCombo->equals(KeyCombo::parse('/')))->toBeTrue();
});

it('the table set reserves pagination on the left and right arrow keys', function () {
    $byKey = collect((new TableSet)->discover(nav([]), page(hasTable: true), 'admin'))
        ->keyBy(fn (ShortcutBinding $b) => $b->target->structureKey);

    expect($byKey->has('page-prev'))->toBeTrue()
        ->and($byKey['page-prev']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowLeft')))->toBeTrue()
        ->and($byKey->has('page-next'))->toBeTrue()
        ->and($byKey['page-next']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowRight')))->toBeTrue();
});

it('the table set mirrors the pagination arrows in a right-to-left panel', function () {
    $byKey = collect((new TableSet(rightToLeft: true))->discover(nav([]), page(hasTable: true), 'admin'))
        ->keyBy(fn (ShortcutBinding $b) => $b->target->structureKey);

    expect($byKey['page-prev']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowRight')))->toBeTrue()
        ->and($byKey['page-next']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowLeft')))->toBeTrue();
});

it('the table set leaves the row arrows alone in a right-to-left panel', function () {
    $byKey = collect((new TableSet(rightToLeft: true))->discover(nav([]), page(hasTable: true), 'admin'))
        ->keyBy(fn (ShortcutBinding $b) => $b->target->structureKey);

    expect($byKey['row-up']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowUp')))->toBeTrue()
        ->and($byKey['row-down']->keyCombo->equals(new KeyCombo(ModifierScheme::none(), 'ArrowDown')))->toBeTrue();
});

// --- Page set: registered but dormant until page discovery is wired ---------------------

it('the page set is keyed "page"', function () {
    expect((new PageSet)->key())->toBe('page');
});

it('the page set discovers nothing until page discovery is wired', function () {
    expect((new PageSet)->discover(nav([]), page(actions: [
        new ActionTarget('create', 'Create', '#create'),
    ], hasTable: true), 'admin'))->toBe([]);
});
