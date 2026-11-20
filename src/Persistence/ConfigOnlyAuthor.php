<?php

namespace Aristonis\FilamentShortcutKeys\Persistence;

use Aristonis\FilamentShortcutKeys\Core\Contracts\SystemMapAuthor;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;

/** Shared-map authoring in 'config' mode, where there are no stored maps to author: every write is refused. */
final class ConfigOnlyAuthor implements SystemMapAuthor
{
    public function createPreset(string $panelId): int
    {
        throw EditingLockedException::forPanel($panelId);
    }

    public function clonePreset(string $panelId, int $sourceMapId): int
    {
        throw EditingLockedException::forPanel($panelId);
    }

    public function saveEntry(string $panelId, int $mapId, MapEntryEdit $edit): int
    {
        throw EditingLockedException::forPanel($panelId);
    }

    public function makeDefault(string $panelId, int $mapId): int
    {
        throw EditingLockedException::forPanel($panelId);
    }
}
