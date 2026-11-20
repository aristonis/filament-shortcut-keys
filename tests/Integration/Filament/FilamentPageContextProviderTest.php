<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Filament\FilamentPageContextProvider;
use Aristonis\FilamentShortcutKeys\Tests\Support\Dom\SelectorProbe;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\CreateOrder;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ListOrders;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ViewOrder;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

function orderTarget(array $targets, string $name): ActionTarget
{
    $match = collect($targets)->firstWhere('name', $name);

    expect($match)->not->toBeNull("no target named '{$name}'");

    return $match;
}

it('reads header actions, table presence, and row actions from a table page', function () {
    $page = Livewire::test(ListOrders::class)->instance();

    $provider = new FilamentPageContextProvider($page);

    expect($provider)->toBeInstanceOf(PageContextProvider::class);

    // Header actions → ActionTarget[], includes the page's 'export' action.
    expect($provider->actions())->each->toBeInstanceOf(ActionTarget::class);
    expect(collect($provider->actions())->pluck('name'))->toContain('export');

    // The list page renders a table.
    expect($provider->hasTable())->toBeTrue();

    // Row (record) actions → ActionTarget[] with the two named record actions.
    $rowActions = $provider->rowActions();
    expect($rowActions)->each->toBeInstanceOf(ActionTarget::class);
    expect(collect($rowActions)->pluck('name')->all())->toContain('approve', 'reject');
});

it('reports no table and no row actions on a page with no table anywhere', function () {
    $page = Livewire::test(CreateOrder::class)->instance();

    $provider = new FilamentPageContextProvider($page);

    expect($provider->hasTable())->toBeFalse()
        ->and($provider->hasNestedTables())->toBeFalse()
        ->and($provider->rowActions())->toBe([]);
});

it('counts a relation manager table as a table on the record page', function () {
    $order = Order::create(['name' => 'Probe order']);

    $provider = new FilamentPageContextProvider(Livewire::test(ViewOrder::class, ['record' => $order->getKey()])->instance());

    // The page owns no table, so it cannot list the relation manager's actions; it can only say the
    // table is there.
    expect($provider->hasTable())->toBeTrue()
        ->and($provider->hasNestedTables())->toBeTrue()
        ->and($provider->rowActions())->toBe([]);
});

it('counts a table widget as a table on the dashboard', function () {
    $provider = new FilamentPageContextProvider(Livewire::test(Dashboard::class)->instance());

    expect($provider->hasTable())->toBeTrue()
        ->and($provider->hasNestedTables())->toBeTrue();
});

it('reports no nested table on a list page', function () {
    $provider = new FilamentPageContextProvider(Livewire::test(ListOrders::class)->instance());

    expect($provider->hasNestedTables())->toBeFalse();
});

it('hands a header action with a url to the client as a url, not a selector', function () {
    $page = Livewire::test(ListOrders::class)->instance();

    $create = orderTarget((new FilamentPageContextProvider($page))->actions(), 'create');

    expect($create->url)->toBe(OrderResource::getUrl('create'))
        ->and($create->selector)->toBeNull();
});

it('keeps the livewire selector for a header action that has no url', function () {
    $page = Livewire::test(ListOrders::class)->instance();

    $export = orderTarget((new FilamentPageContextProvider($page))->actions(), 'export');

    expect($export->url)->toBeNull()
        ->and($export->selector)->toBe('[wire\\:click^="mountAction(\'export\'"]');
});

/**
 * The regression the index-only fixture hid. Every selector the adapter emits has to match the control
 * Filament actually rendered — link-shaped or button-shaped — so the assertion is made against the
 * page's own HTML rather than against another string built the same way.
 */
it('emits header selectors that match the rendered controls', function () {
    Order::create(['name' => 'Probe order']);

    $rendered = Livewire::test(ListOrders::class);
    $html = $rendered->html();

    foreach ((new FilamentPageContextProvider($rendered->instance()))->actions() as $target) {
        if ($target->selector === null) {
            continue;
        }

        expect(SelectorProbe::countMatches($html, $target->selector))
            ->toBeGreaterThan(0, "header action '{$target->name}' emits a selector that matches nothing");
    }
});

it('emits row-action selectors that match both link and button controls', function () {
    Order::create(['name' => 'Probe order']);

    $rendered = Livewire::test(ListOrders::class);
    $html = $rendered->html();

    $targets = (new FilamentPageContextProvider($rendered->instance()))->rowActions();

    // View and Edit resolve to the resource's own pages, so Filament renders them as anchors with no
    // wire:click at all; approve and reject stay Livewire buttons. One selector shape has to reach both.
    expect(collect($targets)->pluck('name')->all())->toContain('view', 'edit', 'approve');

    foreach ($targets as $target) {
        expect($target->url)->toBeNull("row action '{$target->name}' must be found in the focused row, not navigated server-side");

        expect(SelectorProbe::countMatches($html, (string) $target->selector))
            ->toBeGreaterThan(0, "row action '{$target->name}' emits a selector that matches nothing");
    }
});

/**
 * The table set hardcodes edit and delete rather than reading them off the page, so its client-side
 * selectors are not covered by the row-action assertions above and need their own guard.
 */
it('matches the table set built-in edit and delete controls', function () {
    Order::create(['name' => 'Probe order']);

    $html = Livewire::test(ListOrders::class)->html();

    foreach (['edit', 'delete'] as $behavior) {
        expect(SelectorProbe::countMatches($html, '[wire\\:key*=".actions.' . $behavior . '."]'))
            ->toBeGreaterThan(0, "the table set's '{$behavior}' key has no control to click");
    }
});

it('flags a header action that runs the moment it is clicked', function () {
    $actions = collect((new FilamentPageContextProvider(Livewire::test(ListOrders::class)->instance()))->actions())
        ->keyBy('name');

    expect($actions['ping']->runsImmediately)->toBeTrue()
        ->and($actions['export']->runsImmediately)->toBeFalse()   // asks for confirmation first
        ->and($actions['create']->runsImmediately)->toBeFalse();  // navigates
});

it('leaves out header actions the page does not render or that cannot be clicked', function () {
    $names = collect((new FilamentPageContextProvider(Livewire::test(ListOrders::class)->instance()))->actions())
        ->pluck('name')
        ->all();

    expect($names)->toContain('export')
        ->not->toContain('secret')   // ->visible(false)
        ->not->toContain('gated')    // ->visible(fn) that fails for this user
        ->not->toContain('locked');  // ->disabled()
});
