<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\SystemMapPayloadNotAllowedException;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapEntry;
use Illuminate\Support\Facades\DB;

/** Eloquent-backed {@see SystemMapAuthor}: creates, clones, and edits a panel's shared system presets in place. */
final class EloquentSystemMapAuthor implements SystemMapAuthor
{
    public function createPreset(string $panelId, ?array $modifiers = null): int
    {
        // A new preset starts empty; conventions fill the rest at resolve time. It never claims the
        // panel's default marker, since choosing the active/default preset is a separate concern.
        return ShortcutMap::query()->create([
            'panel_id' => $panelId,
            'type' => MapType::SYSTEM,
            'modifiers' => $modifiers,
            'version' => 1,
            'default_marker' => null,
        ])->id;
    }

    public function clonePreset(string $panelId, int $sourceMapId): int
    {
        return DB::transaction(function () use ($panelId, $sourceMapId): int {
            $source = $this->systemMapInPanel($panelId, $sourceMapId);

            $clone = ShortcutMap::query()->create([
                'panel_id' => $panelId,
                'type' => MapType::SYSTEM,
                'modifiers' => $source->modifiers,
                // Version stays monotonic per lineage so a cache key keyed on (id, version) can never
                // alias the clone with its source.
                'version' => $source->version + 1,
                'default_marker' => null,
            ]);

            $rows = $source->entries
                ->map(fn (ShortcutMapEntry $entry): array => [
                    'target' => $entry->target,
                    'letter' => $entry->letter,
                    'disabled' => $entry->disabled,
                    'payload' => $entry->payload,
                ])
                ->all();

            if ($rows !== []) {
                $clone->entries()->createMany($rows);
            }

            return $clone->id;
        });
    }

    public function saveEntry(string $panelId, int $mapId, MapEntryEdit $edit): int
    {
        if ($edit->payload !== null) {
            // A shared system preset only carries remap/disable overrides. Custom-binding payloads
            // (selector/route) are a personal-map feature; accepting one here would publish an
            // unvalidated activation to every admin on the map.
            throw SystemMapPayloadNotAllowedException::forTarget($edit->target);
        }

        return DB::transaction(function () use ($panelId, $mapId, $edit): int {
            $map = $this->systemMapInPanel($panelId, $mapId, forUpdate: true);

            $map->entries()->updateOrCreate(
                ['target' => $edit->target],
                ['letter' => $edit->letter, 'disabled' => $edit->disabled, 'payload' => $edit->payload],
            );

            $this->bumpVersion($map);

            return $map->id;
        });
    }

    public function removeEntry(string $panelId, int $mapId, string $target): int
    {
        return DB::transaction(function () use ($panelId, $mapId, $target): int {
            $map = $this->systemMapInPanel($panelId, $mapId, forUpdate: true);

            $map->entries()->where('target', $target)->delete();
            $this->bumpVersion($map);

            return $map->id;
        });
    }

    /**
     * Load a system map, scoped to its panel. The panel scope is the security boundary: a manager
     * authorized for one panel passing another panel's map id gets a not-found, never a cross-panel
     * edit. A custom (user-owned) map is likewise out of reach here.
     *
     * A system preset is authored concurrently by several admins, so a write locks the map row first:
     * two edits to the same target then serialize on it rather than colliding on the entries UNIQUE
     * constraint (a raw insert race) or clobbering each other blind.
     */
    private function systemMapInPanel(string $panelId, int $mapId, bool $forUpdate = false): ShortcutMap
    {
        return ShortcutMap::query()
            ->with('entries')
            ->whereKey($mapId)
            ->where('panel_id', $panelId)
            ->where('type', MapType::SYSTEM)
            ->when($forUpdate, fn ($query) => $query->lockForUpdate())
            ->firstOrFail();
    }

    private function bumpVersion(ShortcutMap $map): void
    {
        // Every content change bumps the version so the resolver cache, keyed on (map id, version),
        // busts for every admin sharing this system map.
        ShortcutMap::query()->whereKey($map->id)->increment('version');
    }
}
