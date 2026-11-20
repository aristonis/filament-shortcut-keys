<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapSelection;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;
use Aristonis\FilamentShortcutKeys\Tests\Support\Factories\ShortcutMapFactory;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\UuidOwner;
use Illuminate\Database\QueryException;

/** @return array{0: EloquentMapRepository, 1: User} */
function repoAndUser(): array
{
    $user = User::create([
        'name' => 'Owner',
        'email' => uniqid('owner_', true) . '@example.test',
        'password' => 'secret-hash',
    ]);

    return [new EloquentMapRepository, $user];
}

it('resolves the active map from the users selection', function () {
    [$repo, $user] = repoAndUser();

    $custom = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'admin', 'version' => 7]);
    ShortcutMapSelection::create([
        'authenticatable_type' => $user->getMorphClass(),
        'authenticatable_id' => (string) $user->getKey(),
        'panel_id' => 'admin',
        'map_id' => $custom->id,
    ]);

    $active = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'admin');

    expect($active)->toBeInstanceOf(MapData::class)
        ->and($active->type)->toBe(MapType::CUSTOM)
        ->and($active->version)->toBe(7);
});

it('falls back to the panel default system map when the user has no selection', function () {
    [$repo, $user] = repoAndUser();

    ShortcutMapFactory::new()->default('admin')->create(['version' => 4]);

    $active = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'admin');

    expect($active->type)->toBe(MapType::SYSTEM)
        ->and($active->version)->toBe(4);
});

it('returns an empty passthrough map when neither a selection nor a default exists', function () {
    [$repo, $user] = repoAndUser();

    $active = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'ghost-panel');

    expect($active->type)->toBe(MapType::SYSTEM)
        ->and($active->entries)->toBe([])
        ->and($active->version)->toBe(0);
});

it('maps mixed letter and disabled entry rows into the correct entries shape', function () {
    [$repo, $user] = repoAndUser();

    $map = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'admin']);
    ShortcutMapEntry::create(['map_id' => $map->id, 'target' => 'navigation:products', 'letter' => 'x', 'disabled' => false]);
    ShortcutMapEntry::create(['map_id' => $map->id, 'target' => 'navigation:orders', 'letter' => null, 'disabled' => true]);
    ShortcutMapSelection::create([
        'authenticatable_type' => $user->getMorphClass(),
        'authenticatable_id' => (string) $user->getKey(),
        'panel_id' => 'admin',
        'map_id' => $map->id,
    ]);

    $active = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'admin');

    expect($active->entries)->toHaveCount(2)
        ->and($active->entries['navigation:products'])->toBe(['letter' => 'x'])
        ->and($active->entries['navigation:orders'])->toBe(['disabled' => true]);
});

it('keeps selections independent across panels for the same owner', function () {
    [$repo, $user] = repoAndUser();

    $adminDefault = ShortcutMapFactory::new()->default('admin')->create();
    ShortcutMapFactory::new()->default('blog')->create();

    $adminCustom = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'admin']);
    $blogCustom = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'blog']);

    foreach ([['admin', $adminCustom], ['blog', $blogCustom]] as [$panel, $map]) {
        ShortcutMapSelection::create([
            'authenticatable_type' => $user->getMorphClass(),
            'authenticatable_id' => (string) $user->getKey(),
            'panel_id' => $panel,
            'map_id' => $map->id,
        ]);
    }

    // Re-point only the admin selection at the admin default system map.
    ShortcutMapSelection::query()
        ->where('authenticatable_type', $user->getMorphClass())
        ->where('authenticatable_id', (string) $user->getKey())
        ->where('panel_id', 'admin')
        ->update(['map_id' => $adminDefault->id]);

    $admin = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'admin');
    $blog = $repo->activeMap($user->getMorphClass(), (string) $user->getKey(), 'blog');

    expect($admin->type)->toBe(MapType::SYSTEM)
        ->and($blog->type)->toBe(MapType::CUSTOM)
        ->and($blog->entries)->toBe([]);
});

it('forks the active map exactly once across repeated edits', function () {
    [$repo, $user] = repoAndUser();

    $default = ShortcutMapFactory::new()->default('admin')->create();
    ShortcutMapEntry::create(['map_id' => $default->id, 'target' => 'navigation:products', 'letter' => 'p', 'disabled' => false]);
    ShortcutMapEntry::create(['map_id' => $default->id, 'target' => 'navigation:orders', 'letter' => null, 'disabled' => true]);

    $firstId = $repo->saveEntry($user->getMorphClass(), (string) $user->getKey(), 'admin', new MapEntryEdit('navigation:invoices', 'i', false));
    $secondId = $repo->saveEntry($user->getMorphClass(), (string) $user->getKey(), 'admin', new MapEntryEdit('navigation:invoices', 'v', false));
    $first = ShortcutMap::query()->findOrFail($firstId);

    expect($firstId)->toBe($secondId)
        ->and($first->type)->toBe(MapType::CUSTOM)
        ->and($first->authenticatable_type)->toBe($user->getMorphClass())
        ->and((string) $first->authenticatable_id)->toBe((string) $user->getKey())
        // Monotonic per lineage: the source default sits at version 1, so the fork starts at 2 —
        // never sharing a version with its source — and each of the two edits bumps it once more.
        ->and($first->version)->toBe(4);

    // Exactly one custom map owned by (owner, panel).
    $owned = ShortcutMap::query()
        ->where('type', MapType::CUSTOM)
        ->where('authenticatable_type', $user->getMorphClass())
        ->where('authenticatable_id', (string) $user->getKey())
        ->where('panel_id', 'admin')
        ->get();
    expect($owned)->toHaveCount(1);

    // Selection points at the forked map, which holds the source's two entries plus the edit.
    $selection = ShortcutMapSelection::query()
        ->where('authenticatable_type', $user->getMorphClass())
        ->where('authenticatable_id', (string) $user->getKey())
        ->where('panel_id', 'admin')
        ->firstOrFail();
    expect($selection->map_id)->toBe($first->id)
        ->and($first->entries()->count())->toBe(3);
});

it('rejects a second default system map for the same panel', function () {
    ShortcutMapFactory::new()->default('admin')->create();

    expect(violatesConstraint(fn () => ShortcutMapFactory::new()->default('admin')->create()))
        ->toThrow(QueryException::class);
});

it('rejects a duplicate selection for the same owner and panel', function () {
    [, $user] = repoAndUser();
    $map = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'admin']);

    $payload = [
        'authenticatable_type' => $user->getMorphClass(),
        'authenticatable_id' => (string) $user->getKey(),
        'panel_id' => 'admin',
        'map_id' => $map->id,
    ];
    ShortcutMapSelection::create($payload);

    expect(violatesConstraint(fn () => ShortcutMapSelection::create($payload)))->toThrow(QueryException::class);
});

it('rejects a duplicate entry for the same map and target', function () {
    [, $user] = repoAndUser();
    $map = ShortcutMapFactory::new()->custom()->ownedBy($user)->create(['panel_id' => 'admin']);

    ShortcutMapEntry::create(['map_id' => $map->id, 'target' => 'navigation:products', 'letter' => 'x']);

    expect(violatesConstraint(fn () => ShortcutMapEntry::create(['map_id' => $map->id, 'target' => 'navigation:products', 'letter' => 'y'])))
        ->toThrow(QueryException::class);
});

it('round-trips a string (uuid) owner id through the polymorphic morph design', function () {
    $repo = new EloquentMapRepository;
    $owner = UuidOwner::create(['name' => 'Uuid Owner']);

    expect($owner->getKey())->toBeString()
        ->and($owner->getKey())->not->toBeNumeric();

    $default = ShortcutMapFactory::new()->default('admin')->create();
    ShortcutMapEntry::create(['map_id' => $default->id, 'target' => 'navigation:products', 'letter' => 'p']);

    // With no selection the uuid owner resolves to the panel default.
    $before = $repo->activeMap($owner->getMorphClass(), (string) $owner->getKey(), 'admin');
    expect($before->type)->toBe(MapType::SYSTEM);

    $customId = $repo->saveEntry($owner->getMorphClass(), (string) $owner->getKey(), 'admin', new MapEntryEdit('navigation:orders', 'o', false));
    $custom = ShortcutMap::query()->findOrFail($customId);

    // The full uuid string is persisted verbatim — no truncation or numeric coercion.
    expect($custom->type)->toBe(MapType::CUSTOM)
        ->and($custom->authenticatable_type)->toBe($owner->getMorphClass())
        ->and($custom->authenticatable_id)->toBe((string) $owner->getKey());

    $selection = ShortcutMapSelection::query()
        ->where('authenticatable_type', $owner->getMorphClass())
        ->where('authenticatable_id', (string) $owner->getKey())
        ->where('panel_id', 'admin')
        ->firstOrFail();
    expect($selection->map_id)->toBe($custom->id)
        ->and($selection->authenticatable_id)->toBe((string) $owner->getKey());

    // activeMap now resolves the uuid owner to the forked custom map, entries copied.
    $after = $repo->activeMap($owner->getMorphClass(), (string) $owner->getKey(), 'admin');
    expect($after->type)->toBe(MapType::CUSTOM)
        ->and($after->entries)->toHaveCount(2)
        ->and($after->entries['navigation:products'])->toBe(['letter' => 'p']);
});
