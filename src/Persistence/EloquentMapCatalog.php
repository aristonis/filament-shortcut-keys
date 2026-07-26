<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Core\Contracts\ListMaps;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapSummary;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMap;
use Aristonis\FilamentShortcutKeys\Models\ShortcutMapSelection;
use Illuminate\Database\Eloquent\Builder;

/** Eloquent-backed {@see ListMaps} and {@see MapSelector}: what an owner may pick, and pointing their selection at one. */
final class EloquentMapCatalog implements ListMaps, MapSelector
{
    public function available(string $authType, string $authId, string $panelId): array
    {
        $maps = $this->selectableMaps($authType, $authId, $panelId)->orderBy('id')->get();

        $selectedId = ShortcutMapSelection::query()
            ->where('authenticatable_type', $authType)
            ->where('authenticatable_id', $authId)
            ->where('panel_id', $panelId)
            ->value('map_id');

        return $maps->map(function (ShortcutMap $map) use ($panelId, $selectedId): MapSummary {
            $isDefault = $map->default_marker === $panelId;

            return new MapSummary(
                id: $map->id,
                type: $map->type,
                // With no explicit selection the owner resolves to the default preset, so mark that
                // as active to match what activeMap() actually serves.
                isActive: $selectedId === null ? $isDefault : $map->id === $selectedId,
                isDefault: $isDefault,
            );
        })->all();
    }

    public function select(string $authType, string $authId, string $panelId, int $mapId): int
    {
        // Scope the lookup so an owner can only select a system preset or their OWN custom map in
        // this panel; anything else (another panel, another user's map) comes back as not found.
        $map = $this->selectableMaps($authType, $authId, $panelId)->whereKey($mapId)->firstOrFail();

        ShortcutMapSelection::query()->updateOrCreate(
            ['authenticatable_type' => $authType, 'authenticatable_id' => $authId, 'panel_id' => $panelId],
            ['map_id' => $map->id],
        );

        return $map->id;
    }

    /** @return Builder<ShortcutMap> */
    private function selectableMaps(string $authType, string $authId, string $panelId): Builder
    {
        return ShortcutMap::query()
            ->where('panel_id', $panelId)
            ->where(function (Builder $query) use ($authType, $authId): void {
                $query->where('type', MapType::SYSTEM)
                    ->orWhere(function (Builder $owned) use ($authType, $authId): void {
                        $owned->where('type', MapType::CUSTOM)
                            ->where('authenticatable_type', $authType)
                            ->where('authenticatable_id', $authId);
                    });
            });
    }
}
