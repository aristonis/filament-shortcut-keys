<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapEntryEdit;

/**
 * Writes a user's own overrides. Each call first forks the active map into a user-owned custom map if
 * one does not exist yet, then applies the change, so an edit never mutates a shared system map.
 * Kept separate from the read-only {@see MapRepository} so consumers depend only on what they use.
 */
interface MapEditor
{
    /** Upsert one override row on the user's custom map; returns that map's id. */
    public function saveEntry(string $authType, string $authId, string $panelId, MapEntryEdit $edit): int;

    /** Drop one override row (reverting the target to convention); returns the user's custom map id. */
    public function removeEntry(string $authType, string $authId, string $panelId, string $target): int;
}
