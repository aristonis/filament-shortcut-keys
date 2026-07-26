<?php

use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Panel;
use Filament\PanelRegistry;

/** A map carrying one override per case the audit has to tell apart. */
function mapWithMixedEntries(): ShortcutMap
{
    $map = ShortcutMap::factory()->default('admin')->create();

    $map->entries()->createMany([
        ['target' => 'navigation:' . OrderResource::class, 'letter' => 'o'],
        ['target' => 'navigation:App\\Filament\\Resources\\DeletedResource', 'letter' => 'd'],
        ['target' => 'row-action:approve', 'letter' => 'a'],
        ['target' => 'row-action:archive', 'letter' => 'r'],
        ['target' => 'table:search', 'disabled' => true],
        ['target' => 'global:export', 'letter' => 'e'],
        ['target' => 'custom:reports', 'letter' => 'k', 'payload' => ['route' => '/admin/orders']],
    ]);

    return $map;
}

function remainingTargets(ShortcutMap $map): array
{
    return $map->entries()->orderBy('target')->pluck('target')->all();
}

it('removes only the overrides whose target no longer exists', function () {
    $map = mapWithMixedEntries();

    $this->artisan('shortcut-keys:prune')->assertSuccessful();

    expect(remainingTargets($map))->toBe([
        'custom:reports',
        'global:export',
        'navigation:' . OrderResource::class,
        'row-action:approve',
        'table:search',
    ]);
});

it('bumps the version of every map it prunes so cached keymaps are rebuilt', function () {
    $map = mapWithMixedEntries();
    $before = $map->version;

    $this->artisan('shortcut-keys:prune')->assertSuccessful();

    expect($map->fresh()->version)->toBe($before + 1);
});

it('leaves an untouched map at its current version', function () {
    $map = ShortcutMap::factory()->default('admin')->create();
    $map->entries()->create(['target' => 'navigation:' . OrderResource::class, 'letter' => 'o']);
    $before = $map->version;

    $this->artisan('shortcut-keys:prune')->assertSuccessful();

    expect($map->fresh()->version)->toBe($before);
});

it('changes nothing on a dry run', function () {
    $map = mapWithMixedEntries();

    $this->artisan('shortcut-keys:prune --dry-run')->assertSuccessful();

    expect($map->entries()->count())->toBe(7)
        ->and($map->fresh()->version)->toBe($map->version);
});

it('leaves maps belonging to a panel it was not asked to audit alone', function () {
    $other = ShortcutMap::factory()->create(['panel_id' => 'reporting']);
    $other->entries()->create(['target' => 'navigation:App\\Filament\\Resources\\DeletedResource', 'letter' => 'd']);

    $this->artisan('shortcut-keys:prune --panel=admin')->assertSuccessful();

    expect($other->entries()->count())->toBe(1);
});

it('fails loudly when asked to audit a panel that is not registered', function () {
    $this->artisan('shortcut-keys:prune --panel=ghost')->assertFailed();
});

/** A second panel in the same app that never registered the plugin, so it owns no shortcut data. */
function registerPluginlessPanel(): void
{
    app(PanelRegistry::class)->register(Panel::make()->id('reporting')->path('reporting'));
}

it('skips a registered panel that does not use the plugin', function () {
    registerPluginlessPanel();
    $map = mapWithMixedEntries();

    $this->artisan('shortcut-keys:prune')->assertSuccessful();

    expect(remainingTargets($map))->toHaveCount(5);
});

it('fails loudly when asked to audit a panel that does not use the plugin', function () {
    registerPluginlessPanel();

    $this->artisan('shortcut-keys:prune --panel=reporting')->assertFailed();
});

it('asks before deleting in production and honours a refusal', function () {
    $map = mapWithMixedEntries();
    app()['env'] = 'production';

    $this->artisan('shortcut-keys:prune')
        ->expectsConfirmation('Are you sure you want to run this command?', 'no')
        ->assertFailed();

    expect($map->entries()->count())->toBe(7);
});

it('deletes in production without asking when forced', function () {
    $map = mapWithMixedEntries();
    app()['env'] = 'production';

    $this->artisan('shortcut-keys:prune --force')->assertSuccessful();

    expect($map->entries()->count())->toBe(5);
});

it('reports what it removed', function () {
    mapWithMixedEntries();

    $this->artisan('shortcut-keys:prune')
        ->expectsOutputToContain('navigation:App\\Filament\\Resources\\DeletedResource')
        ->assertSuccessful();
});

it('succeeds with nothing to do when no entries are stored', function () {
    $this->artisan('shortcut-keys:prune')->assertSuccessful();

    expect(ShortcutMapEntry::count())->toBe(0);
});
