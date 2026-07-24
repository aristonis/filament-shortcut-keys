<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapSelection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Eloquent-backed {@see MapRepository}: resolves the active map and forks it for editing. */
final class EloquentMapRepository implements MapRepository
{
    public function activeMap(string $authType, string $authId, string $panelId): MapData
    {
        $map = $this->resolveActiveMap($authType, $authId, $panelId);

        if ($map === null) {
            // An app with no seeded system map still resolves via pure conventions
            // downstream, so we hand back a neutral passthrough rather than throwing.
            return new MapData(MapType::SYSTEM, null, [], 0);
        }

        return $this->toMapData($map);
    }

    /**
     * Idempotently produce the user-owned custom map the edit flow mutates.
     *
     * Invariant: calling this any number of times for one (owner, panel) yields exactly
     * one custom map, with the user's selection pointing at it and no orphans.
     *
     * Concurrency: the whole flow runs in one transaction, split by whether a selection row
     * already exists. When it does, we hold its row lock (lockForUpdate) and repoint it
     * directly — no race. When it does NOT, there is no row to lock, so the selections
     * UNIQUE(owner, panel) constraint is the serialization point: we insert with a plain
     * create() (which, unlike updateOrCreate, does not swallow the violation), and a losing
     * concurrent fork catches the error, discards its map, and converges on the winner's.
     */
    public function forkForEdit(string $authType, string $authId, string $panelId): ShortcutMap
    {
        return DB::transaction(function () use ($authType, $authId, $panelId): ShortcutMap {
            // Lock the selection row if it exists so a concurrent fork blocks until we commit.
            // A lockForUpdate over a non-existent row locks nothing — which is exactly why the
            // no-selection branch relies on the unique constraint, not the lock.
            $lockedId = ShortcutMapSelection::query()
                ->where('authenticatable_type', $authType)
                ->where('authenticatable_id', $authId)
                ->where('panel_id', $panelId)
                ->lockForUpdate()
                ->value('id');

            if ($lockedId === null) {
                return $this->claimNewSelection($authType, $authId, $panelId);
            }

            $selection = ShortcutMapSelection::query()->whereKey($lockedId)->firstOrFail();
            $active = $selection->map()->with('entries')->first();

            if ($active !== null
                && $active->type === MapType::CUSTOM
                && $active->authenticatable_type === $authType
                && (string) $active->authenticatable_id === $authId) {
                return $active; // Idempotent hit — reuse the existing custom map.
            }

            return $this->claimExistingSelection($selection, $active, $authType, $authId, $panelId);
        });
    }

    /**
     * We already hold this selection's row lock, so no concurrent fork can touch it: replicate
     * the source into a fresh custom map and repoint the locked selection directly.
     */
    private function claimExistingSelection(
        ShortcutMapSelection $selection,
        ?ShortcutMap $source,
        string $authType,
        string $authId,
        string $panelId,
    ): ShortcutMap {
        $custom = $this->replicateAsCustom($source, $authType, $authId, $panelId);
        $selection->update(['map_id' => $custom->id]);

        return $custom;
    }

    /**
     * No selection row exists to lock, so the UNIQUE(owner, panel) constraint is the
     * serialization point. We create the custom map, then claim the slot with a plain
     * create(); if a concurrent fork inserted first, that create() throws, we drop our
     * now-redundant map, and converge on the winner's map.
     */
    private function claimNewSelection(string $authType, string $authId, string $panelId): ShortcutMap
    {
        $source = $this->defaultSystemMap($panelId);
        $custom = $this->replicateAsCustom($source, $authType, $authId, $panelId);

        try {
            ShortcutMapSelection::query()->create([
                'authenticatable_type' => $authType,
                'authenticatable_id' => $authId,
                'panel_id' => $panelId,
                'map_id' => $custom->id,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $custom->delete();

            $winner = ShortcutMapSelection::query()
                ->where('authenticatable_type', $authType)
                ->where('authenticatable_id', $authId)
                ->where('panel_id', $panelId)
                ->firstOrFail();

            return $winner->map()->with('entries')->firstOrFail();
        }

        return $custom;
    }

    private function resolveActiveMap(string $authType, string $authId, string $panelId): ?ShortcutMap
    {
        $selection = ShortcutMapSelection::query()
            ->where('authenticatable_type', $authType)
            ->where('authenticatable_id', $authId)
            ->where('panel_id', $panelId)
            ->first();

        if ($selection !== null) {
            return $selection->map()->with('entries')->first();
        }

        return $this->defaultSystemMap($panelId);
    }

    private function defaultSystemMap(string $panelId): ?ShortcutMap
    {
        return ShortcutMap::query()
            ->with('entries')
            ->where('default_marker', $panelId)
            ->first();
    }

    private function replicateAsCustom(?ShortcutMap $source, string $authType, string $authId, string $panelId): ShortcutMap
    {
        $custom = ShortcutMap::query()->create([
            'panel_id' => $panelId,
            'type' => MapType::CUSTOM,
            'authenticatable_type' => $authType,
            'authenticatable_id' => $authId,
            'modifiers' => $source?->modifiers,
            // Version is monotonic per lineage: the fork must never share its source's
            // version, or a later cache key keyed on version could alias two maps that hold
            // different content.
            'version' => $source === null ? 1 : $source->version + 1,
            'default_marker' => null,
        ]);

        if ($source === null) {
            return $custom;
        }

        $rows = $source->entries
            ->map(fn (ShortcutMapEntry $entry): array => [
                'target' => $entry->target,
                'letter' => $entry->letter,
                'disabled' => $entry->disabled,
            ])
            ->all();

        if ($rows !== []) {
            $custom->entries()->createMany($rows);
        }

        return $custom;
    }

    private function toMapData(ShortcutMap $map): MapData
    {
        $entries = [];

        foreach ($map->entries as $entry) {
            if ($entry->letter !== null) {
                $entries[$entry->target] = ['letter' => $entry->letter];
            } elseif ($entry->disabled) {
                $entries[$entry->target] = ['disabled' => true];
            }
        }

        return new MapData(
            type: $map->type,
            modifiers: $map->modifiers,
            entries: $entries,
            version: $map->version,
            id: $map->id,
        );
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        // 23000/23505 are the ANSI SQLSTATE codes MySQL/pgsql/sqlite report for a
        // unique-constraint breach.
        return in_array($exception->getCode(), ['23000', '23505'], true);
    }
}
