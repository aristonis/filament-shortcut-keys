<?php

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Filament\FilamentPageContextProvider;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ListOrders;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

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

it('reports no table and no row actions on a non-table page', function () {
    $page = Livewire::test(Dashboard::class)->instance();

    $provider = new FilamentPageContextProvider($page);

    expect($provider->hasTable())->toBeFalse()
        ->and($provider->rowActions())->toBe([]);
});
