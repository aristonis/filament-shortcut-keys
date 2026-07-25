<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;

/**
 * Authors a panel's shared SYSTEM maps. Where {@see MapEditor} forks a personal copy on first write
 * so a user never touches a shared map, every operation here mutates a system preset that all admins
 * on that map see. Callers are expected to have passed the developer's authoring gate first; each
 * write is scoped to a panel so one panel's manager cannot reach another's maps.
 */
interface SystemMapAuthor
{
    /** Create a new, empty system preset for a panel; returns its id. */
    public function createPreset(string $panelId, ?array $modifiers = null): int;

    /** Copy an existing system preset (its modifiers and override rows) into a fresh preset; returns the new id. */
    public function clonePreset(string $panelId, int $sourceMapId): int;

    /** Upsert one override row on a system preset in place; returns that map's id. */
    public function saveEntry(string $panelId, int $mapId, MapEntryEdit $edit): int;

    /** Drop one override row from a system preset in place; returns that map's id. */
    public function removeEntry(string $panelId, int $mapId, string $target): int;
}
