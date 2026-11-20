<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\SystemMapPayloadNotAllowedException;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentSystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Tests\Support\Factories\ShortcutMapFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('creates an empty system preset that never claims the default marker', function () {
    $id = (new EloquentSystemMapAuthor)->createPreset('admin');

    $map = ShortcutMap::query()->findOrFail($id);

    expect($map->type)->toBe(MapType::SYSTEM)
        ->and($map->panel_id)->toBe('admin')
        ->and($map->default_marker)->toBeNull()
        ->and($map->entries)->toHaveCount(0);
});

it('clones a preset with its entries into a fresh system map of a higher version', function () {
    $source = ShortcutMapFactory::new()->create(['version' => 3]);
    $source->entries()->createMany([
        ['target' => 'navigation:orders', 'letter' => 'o', 'disabled' => false, 'payload' => null],
        ['target' => 'global:export', 'letter' => null, 'disabled' => true, 'payload' => null],
    ]);

    $cloneId = (new EloquentSystemMapAuthor)->clonePreset('admin', $source->id);
    $clone = ShortcutMap::query()->with('entries')->findOrFail($cloneId);

    expect($cloneId)->not->toBe($source->id)
        ->and($clone->type)->toBe(MapType::SYSTEM)
        ->and($clone->version)->toBe(4)
        ->and($clone->default_marker)->toBeNull()
        ->and($clone->entries->pluck('target')->all())->toContain('navigation:orders', 'global:export');
});

it('edits a system preset in place and bumps its version', function () {
    $map = ShortcutMapFactory::new()->create(['version' => 2]);

    $id = (new EloquentSystemMapAuthor)->saveEntry('admin', $map->id, new MapEntryEdit('navigation:orders', 'x', false));

    $map->refresh()->load('entries');

    expect($id)->toBe($map->id)
        ->and($map->version)->toBe(3)
        ->and($map->entries->firstWhere('target', 'navigation:orders')->letter)->toBe('x');
});

it('refuses to reach a map in another panel', function () {
    $map = ShortcutMapFactory::new()->create(['panel_id' => 'reports']);

    expect(fn () => (new EloquentSystemMapAuthor)->saveEntry('admin', $map->id, new MapEntryEdit('navigation:orders', 'o', false)))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses to edit a user-owned custom map through the system author', function () {
    $map = ShortcutMapFactory::new()->custom()->create(['panel_id' => 'admin']);

    expect(fn () => (new EloquentSystemMapAuthor)->saveEntry('admin', $map->id, new MapEntryEdit('navigation:orders', 'o', false)))
        ->toThrow(ModelNotFoundException::class);
});

it('refuses to write a custom-binding payload onto a shared system map', function () {
    $map = ShortcutMapFactory::new()->create(['version' => 2]);

    expect(fn () => (new EloquentSystemMapAuthor)->saveEntry(
        'admin',
        $map->id,
        new MapEntryEdit('custom:pwn', 'k', false, ['route' => '/admin/orders']),
    ))->toThrow(SystemMapPayloadNotAllowedException::class);

    $map->refresh()->load('entries');

    expect($map->version)->toBe(2)
        ->and($map->entries)->toHaveCount(0);
});

it('refuses to clone a source that does not exist in the panel', function () {
    $map = ShortcutMapFactory::new()->create(['panel_id' => 'reports']);

    expect(fn () => (new EloquentSystemMapAuthor)->clonePreset('admin', $map->id))
        ->toThrow(ModelNotFoundException::class);
});

it('makes a preset the panel default and takes the mark off the previous one', function () {
    $previous = ShortcutMapFactory::new()->default('admin')->create();
    $preset = ShortcutMapFactory::new()->create(['panel_id' => 'admin']);

    (new EloquentSystemMapAuthor)->makeDefault('admin', $preset->id);

    expect($preset->fresh()->default_marker)->toBe('admin')
        ->and($previous->fresh()->default_marker)->toBeNull();
});

it('refuses to make a map of another panel, or a custom map, the default', function () {
    $foreign = ShortcutMapFactory::new()->create(['panel_id' => 'reports']);
    $custom = ShortcutMapFactory::new()->custom()->create(['panel_id' => 'admin']);

    expect(fn () => (new EloquentSystemMapAuthor)->makeDefault('admin', $foreign->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => (new EloquentSystemMapAuthor)->makeDefault('admin', $custom->id))->toThrow(ModelNotFoundException::class);
});

it('leaves exactly one default when the current default is chosen again', function () {
    $default = ShortcutMapFactory::new()->default('admin')->create();
    ShortcutMapFactory::new()->create(['panel_id' => 'admin']);

    (new EloquentSystemMapAuthor)->makeDefault('admin', $default->id);

    expect(ShortcutMap::query()->where('default_marker', 'admin')->pluck('id')->all())->toBe([$default->id]);
});

it('keeps the previous default when the new one is refused', function () {
    $previous = ShortcutMapFactory::new()->default('admin')->create();
    $foreign = ShortcutMapFactory::new()->create(['panel_id' => 'reports']);

    try {
        (new EloquentSystemMapAuthor)->makeDefault('admin', $foreign->id);
    } catch (ModelNotFoundException) {
    }

    expect($previous->fresh()->default_marker)->toBe('admin');
});
