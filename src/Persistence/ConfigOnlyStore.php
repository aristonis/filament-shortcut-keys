<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Core\Contracts\ListMaps;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapEditor;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;
use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;

/**
 * An admin's map in 'config' mode. Every map is empty, so the resolver falls through to the
 * conventions and the config overlay, and no query reaches the package's tables, which an app in this
 * mode need not have migrated. Every write is refused.
 */
final class ConfigOnlyStore implements ListMaps, MapEditor, MapRepository, MapSelector
{
    public function activeMap(string $authType, string $authId, string $panelId): MapData
    {
        return new MapData(MapType::SYSTEM, [], 0);
    }

    public function available(string $authType, string $authId, string $panelId): array
    {
        return [];
    }

    public function saveEntry(string $authType, string $authId, string $panelId, MapEntryEdit $edit): int
    {
        throw EditingLockedException::forPanel($panelId);
    }

    public function removeEntry(string $authType, string $authId, string $panelId, string $target): int
    {
        throw EditingLockedException::forPanel($panelId);
    }

    public function select(string $authType, string $authId, string $panelId, int $mapId): int
    {
        throw EditingLockedException::forPanel($panelId);
    }
}
