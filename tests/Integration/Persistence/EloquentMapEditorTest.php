<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;

function editorRepoAndUser(): array
{
    $user = User::create([
        'name' => 'Owner',
        'email' => uniqid('editor_', true) . '@example.test',
        'password' => 'secret-hash',
    ]);

    return [new EloquentMapRepository, $user];
}

it('forks the system map and writes a custom binding with its payload', function () {
    [$repo, $user] = editorRepoAndUser();
    ShortcutMap::factory()->default('admin')->create();

    $mapId = $repo->saveEntry(
        $user->getMorphClass(),
        (string) $user->getKey(),
        'admin',
        new MapEntryEdit('custom:deploy', 'd', false, ['selector' => '#deploy']),
    );

    $map = ShortcutMap::findOrFail($mapId);
    expect($map->type)->toBe(MapType::CUSTOM)
        ->and($map->authenticatable_type)->toBe($user->getMorphClass());

    $entry = ShortcutMapEntry::where('map_id', $mapId)->where('target', 'custom:deploy')->firstOrFail();
    expect($entry->letter)->toBe('d')
        ->and($entry->disabled)->toBeFalse()
        ->and($entry->payload)->toBe(['selector' => '#deploy']);
});

it('upserts the same target rather than duplicating it', function () {
    [$repo, $user] = editorRepoAndUser();
    ShortcutMap::factory()->default('admin')->create();
    $auth = [$user->getMorphClass(), (string) $user->getKey(), 'admin'];

    $repo->saveEntry(...$auth, edit: new MapEntryEdit('navigation:orders', 'o', false));
    $mapId = $repo->saveEntry(...$auth, edit: new MapEntryEdit('navigation:orders', 'p', false));

    $entries = ShortcutMapEntry::where('map_id', $mapId)->where('target', 'navigation:orders')->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()->letter)->toBe('p');
});

it('removes an override, reverting the target to convention', function () {
    [$repo, $user] = editorRepoAndUser();
    ShortcutMap::factory()->default('admin')->create();
    $auth = [$user->getMorphClass(), (string) $user->getKey(), 'admin'];

    $repo->saveEntry(...$auth, edit: new MapEntryEdit('global:export', null, true));
    $mapId = $repo->removeEntry(...$auth, target: 'global:export');

    expect(ShortcutMapEntry::where('map_id', $mapId)->where('target', 'global:export')->exists())->toBeFalse();
});

it('preserves a payload entry when forking a source map', function () {
    [$repo, $user] = editorRepoAndUser();
    $default = ShortcutMap::factory()->default('admin')->create();
    ShortcutMapEntry::create([
        'map_id' => $default->id,
        'target' => 'custom:reports',
        'letter' => 'j',
        'disabled' => false,
        'payload' => ['route' => '/admin/reports'],
    ]);

    // First edit on an unrelated target forks the system map into the user's custom map.
    $mapId = $repo->saveEntry($user->getMorphClass(), (string) $user->getKey(), 'admin', new MapEntryEdit('navigation:orders', 'o', false));

    $forked = ShortcutMapEntry::where('map_id', $mapId)->where('target', 'custom:reports')->firstOrFail();
    expect($forked->payload)->toBe(['route' => '/admin/reports']);
});

it('bumps the map version on every edit so the cache busts', function () {
    [$repo, $user] = editorRepoAndUser();
    ShortcutMap::factory()->default('admin')->create();
    $auth = [$user->getMorphClass(), (string) $user->getKey(), 'admin'];

    $mapId = $repo->saveEntry(...$auth, edit: new MapEntryEdit('navigation:orders', 'o', false));
    $afterFirst = ShortcutMap::findOrFail($mapId)->version;

    $repo->saveEntry(...$auth, edit: new MapEntryEdit('custom:deploy', 'd', false, ['selector' => '#deploy']));
    $afterSecond = ShortcutMap::findOrFail($mapId)->version;

    expect($afterSecond)->toBeGreaterThan($afterFirst);
});
