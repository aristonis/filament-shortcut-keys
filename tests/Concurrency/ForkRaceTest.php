<?php

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
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

    // The competing request commits its selection in the window between our lock probe (which finds
    // no row to lock) and our own insert — the exact interleaving the UNIQUE constraint exists for.
    $raced = false;
    DB::listen(function ($query) use (&$raced, $system) {
        if ($raced || ! str_contains($query->sql, 'shortcut_map_selections') || ! str_contains(strtolower($query->sql), 'for update')) {
            return;
        }

        $raced = true;

        $this->second()->table('shortcut_map_selections')->insert([
            'authenticatable_type' => 'users',
            'authenticatable_id' => '1',
            'panel_id' => 'admin',
            'map_id' => $system->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $map = (new EloquentMapRepository)->forkForEdit('users', '1', 'admin');

    expect($raced)->toBeTrue('the race never triggered, so this asserted nothing');

    $selections = DB::table('shortcut_map_selections')
        ->where('authenticatable_type', 'users')
        ->where('authenticatable_id', '1')
        ->where('panel_id', 'admin')
        ->count();

    expect($selections)->toBe(1)
        ->and($map->id)->toBe($system->id)
        ->and(ShortcutMap::query()->where('type', MapType::CUSTOM)->count())
        ->toBe(0, 'the losing fork left an orphaned custom map behind');
});
