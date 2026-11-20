<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Aristonis\FilamentShortcutKeys\Persistence\EloquentMapRepository;
use Illuminate\Support\Facades\DB;

/**
 * Two first-time edits by the same admin on the same panel must converge on ONE custom map with
 * neither edit lost. The losing fork discovers the clash through the selections UNIQUE constraint,
 * so this only executes on a driver with real concurrent connections.
 */
it('converges two concurrent first-time forks on a single map', function () {
    // InnoDB gap-locks a `for update` over a missing row, so the competing insert BLOCKS instead of
    // reaching the unique constraint. Driving both connections from one process then deadlocks by
    // construction: the second waits on the first, the first waits on this listener to return.
    // MySQL serialises the race through that gap lock rather than the constraint, so the converge
    // branch is unreachable there and would need two real processes to observe.
    if (DB::connection()->getDriverName() === 'mysql') {
        $this->markTestSkipped('InnoDB gap locks serialise this race; the constraint branch is pgsql-only.');
    }

    $system = ShortcutMap::query()->create([
        'panel_id' => 'admin',
        'type' => MapType::SYSTEM,
        'default_marker' => 'admin',
        'version' => 1,
    ]);

    // The competing request commits its own fork in the window between our lock probe (which finds
    // no row to lock) and our own insert — the exact interleaving the UNIQUE constraint exists for.
    $raced = false;
    $winner = null;
    DB::listen(function ($query) use (&$raced, &$winner) {
        if ($raced || ! str_contains($query->sql, 'shortcut_map_selections') || ! str_contains(strtolower($query->sql), 'for update')) {
            return;
        }

        $raced = true;
        $now = now();

        $winner = $this->second()->table('shortcut_maps')->insertGetId([
            'panel_id' => 'admin',
            'type' => MapType::CUSTOM->value,
            'authenticatable_type' => 'users',
            'authenticatable_id' => '1',
            'version' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->second()->table('shortcut_map_selections')->insert([
            'authenticatable_type' => 'users',
            'authenticatable_id' => '1',
            'panel_id' => 'admin',
            'map_id' => $winner,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    });

    $mapId = (new EloquentMapRepository)->saveEntry('users', '1', 'admin', new MapEntryEdit('navigation:orders', 'o', false));

    expect($raced)->toBeTrue('the race never triggered, so this asserted nothing');

    $selections = DB::table('shortcut_map_selections')
        ->where('authenticatable_type', 'users')
        ->where('authenticatable_id', '1')
        ->where('panel_id', 'admin')
        ->count();

    expect($selections)->toBe(1)
        ->and($mapId)->toBe($winner, 'the edit did not land on the fork that won')
        ->and(ShortcutMap::query()->where('type', MapType::CUSTOM)->count())
        ->toBe(1, 'the losing fork left an orphaned custom map behind')
        ->and(ShortcutMapEntry::query()->where('map_id', $winner)->where('target', 'navigation:orders')->exists())
        ->toBeTrue('the edit was lost along with the losing fork')
        ->and(ShortcutMapEntry::query()->where('map_id', $system->id)->exists())
        ->toBeFalse('the edit leaked into the shared system map')
        ->and($system->fresh()->version)->toBe(1);
});
