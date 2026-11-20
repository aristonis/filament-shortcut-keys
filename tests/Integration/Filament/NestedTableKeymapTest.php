<?php

use Aristonis\FilamentShortcutKeys\Filament\FilamentPageContextProvider;
use Aristonis\FilamentShortcutKeys\Tests\Support\Dom\SelectorProbe;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ViewOrder;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use Aristonis\FilamentShortcutKeys\Tests\Support\Widgets\PendingOrders;
use Livewire\Livewire;

beforeEach(function () {
    ItemsRelationManager::$shown = true;
});

/** The cheatsheet markup the render hook appends after the keymap. */
function renderedOverlay(string $html): string
{
    return substr($html, (int) strpos($html, 'id="filament-shortcut-keys-overlay"'));
}

it('gives a relation manager table the table keys and the registered row actions', function () {
    $order = Order::create(['name' => 'Probe order']);

    $map = collect(injectedKeymap((string) $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->getContent()));

    $rowActions = $map->firstWhere('set', 'row-action');

    expect($map->firstWhere('set', 'table'))->not->toBeNull()
        ->and(array_column($rowActions['bindings'], 'target'))->toContain('row-action:approve', 'row-action:reject');

    $approve = collect($rowActions['bindings'])->firstWhere('target', 'row-action:approve');

    expect($approve['activation'])->toBe(['kind' => 'click', 'selector' => '[wire\\:key*=".actions.approve."]']);
});

it('checks relation manager access no more often than Filament does to render the page', function () {
    $order = Order::create(['name' => 'Probe order']);
    ItemsRelationManager::$accessChecks = 0;

    $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();

    expect(ItemsRelationManager::$accessChecks)->toBe(1);
});

it('gives a table widget the table keys', function () {
    $map = collect(injectedKeymap((string) $this->get('/admin')->assertOk()->getContent()));

    expect($map->firstWhere('set', 'table'))->not->toBeNull()
        ->and(array_column($map->firstWhere('set', 'row-action')['bindings'], 'target'))->toContain('row-action:approve');
});

it('lets the cheatsheet hide a nested row action the page turns out not to have', function () {
    $overlay = renderedOverlay((string) $this->get('/admin')->assertOk()->getContent());

    // The server cannot see inside a lazy widget, so each such row names the control it needs.
    expect($overlay)->toContain('data-fsk-presence="[wire\:key*=&quot;.actions.reject.&quot;]"')
        ->toContain('Reject');
});

it('marks no cheatsheet row on a page whose own table lists its actions', function () {
    $overlay = renderedOverlay((string) $this->get('/admin/orders')->assertOk()->getContent());

    expect($overlay)->not->toContain('data-fsk-presence');
});

/**
 * The client finds rows and their controls by selector across the whole document, so a nested table
 * only works if Filament renders it in the same shape as a page's own table.
 */
function expectCursorAndApproveIn(string $html): void
{
    expect(SelectorProbe::countMatches($html, '[wire\\:key*=".table.records."]'))->toBeGreaterThan(0)
        ->and(SelectorProbe::countMatches($html, FilamentPageContextProvider::rowActionSelector('approve')))->toBeGreaterThan(0);
}

it('renders a relation manager table in the shape the row cursor and row actions look for', function () {
    $order = Order::create(['name' => 'Probe order']);
    $order->items()->create(['name' => 'Probe item']);

    expectCursorAndApproveIn(Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])->html());
});

it('renders a table widget in the shape the row cursor and row actions look for', function () {
    Order::create(['name' => 'Probe order']);

    expectCursorAndApproveIn(Livewire::test(PendingOrders::class)->html());
});

it('gives a header action the same key whether or not the page shows a relation manager', function () {
    $order = Order::create(['name' => 'Probe order']);
    $archiveKey = function () use ($order): string {
        $global = collect(injectedKeymap((string) $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->getContent()))
            ->firstWhere('set', 'global');

        return collect($global['bindings'])->firstWhere('target', 'global:archive')['code'];
    };

    ItemsRelationManager::$shown = false;
    $without = $archiveKey();
    ItemsRelationManager::$shown = true;
    $with = $archiveKey();

    // A belongs to the registered `approve` on every page, so archive never takes it.
    expect($without)->toBe($with)->not->toBe('KeyA');
});

it('keeps header actions off the key the overlay moved a row action to', function () {
    config()->set('shortcut-keys.overlay', ['row-action:approve' => ['letter' => 'x']]);
    $order = Order::create(['name' => 'Probe order']);

    $map = collect(injectedKeymap((string) $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk()->getContent()));
    $codes = collect($map->whereIn('set', ['global', 'row-action'])->flatMap(fn ($group) => $group['bindings']))->pluck('code', 'target');

    // approve moved to X; archive keeps clear of both, so its key does not depend on the pin taking.
    expect($codes['row-action:approve'])->toBe('KeyX')
        ->and($codes['global:archive'])->not->toBeIn(['KeyA', 'KeyX']);
});
