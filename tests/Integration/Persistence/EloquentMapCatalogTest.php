<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapSelection;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapCatalog;
use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function catalogUser(): User
{
    return User::create([
        'name' => 'Owner',
        'email' => uniqid('catalog_', true) . '@example.test',
        'password' => 'secret-hash',
    ]);
}

it('lists the panel system presets and the owner\'s own custom map, and nothing else', function () {
    $user = catalogUser();
    $other = catalogUser();

    $default = ShortcutMap::factory()->default('admin')->create();
    $preset = ShortcutMap::factory()->create(['panel_id' => 'admin']);
    $mine = ShortcutMap::factory()->custom()->ownedBy($user)->create(['panel_id' => 'admin']);
    ShortcutMap::factory()->custom()->ownedBy($other)->create(['panel_id' => 'admin']);   // another user's
    ShortcutMap::factory()->create(['panel_id' => 'reports']);                              // another panel

    $summaries = (new EloquentMapCatalog)->available($user->getMorphClass(), (string) $user->getKey(), 'admin');

    expect(collect($summaries)->pluck('id')->all())->toEqualCanonicalizing([$default->id, $preset->id, $mine->id]);
});

it('marks the default preset active when the owner has made no selection', function () {
    $user = catalogUser();
    $default = ShortcutMap::factory()->default('admin')->create();
    $preset = ShortcutMap::factory()->create(['panel_id' => 'admin']);

    $summaries = collect((new EloquentMapCatalog)->available($user->getMorphClass(), (string) $user->getKey(), 'admin'));

    expect($summaries->firstWhere('id', $default->id))->toMatchObject(['isActive' => true, 'isDefault' => true, 'type' => MapType::SYSTEM])
        ->and($summaries->firstWhere('id', $preset->id))->toMatchObject(['isActive' => false, 'isDefault' => false]);
});

it('marks the explicitly selected map active instead of the default', function () {
    $user = catalogUser();
    $default = ShortcutMap::factory()->default('admin')->create();
    $preset = ShortcutMap::factory()->create(['panel_id' => 'admin']);

    $catalog = new EloquentMapCatalog;
    $catalog->select($user->getMorphClass(), (string) $user->getKey(), 'admin', $preset->id);

    $summaries = collect($catalog->available($user->getMorphClass(), (string) $user->getKey(), 'admin'));

    expect($summaries->firstWhere('id', $preset->id)->isActive)->toBeTrue()
        ->and($summaries->firstWhere('id', $default->id)->isActive)->toBeFalse();
});

it('replaces an existing selection rather than adding a second', function () {
    $user = catalogUser();
    $a = ShortcutMap::factory()->create(['panel_id' => 'admin']);
    $b = ShortcutMap::factory()->create(['panel_id' => 'admin']);
    $catalog = new EloquentMapCatalog;

    $catalog->select($user->getMorphClass(), (string) $user->getKey(), 'admin', $a->id);
    $catalog->select($user->getMorphClass(), (string) $user->getKey(), 'admin', $b->id);

    expect(ShortcutMapSelection::query()->count())->toBe(1)
        ->and(ShortcutMapSelection::query()->value('map_id'))->toBe($b->id);
});

it('refuses to select a map from another panel', function () {
    $user = catalogUser();
    $foreign = ShortcutMap::factory()->create(['panel_id' => 'reports']);

    expect(fn () => (new EloquentMapCatalog)->select($user->getMorphClass(), (string) $user->getKey(), 'admin', $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses to select another user\'s custom map', function () {
    $user = catalogUser();
    $other = catalogUser();
    $theirs = ShortcutMap::factory()->custom()->ownedBy($other)->create(['panel_id' => 'admin']);

    expect(fn () => (new EloquentMapCatalog)->select($user->getMorphClass(), (string) $user->getKey(), 'admin', $theirs->id))
        ->toThrow(ModelNotFoundException::class);
});
