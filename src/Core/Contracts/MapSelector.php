<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

/** Points an owner's active-map selection at a map they are allowed to pick for a panel. */
interface MapSelector
{
    /** Set the owner's active map for the panel; returns the selected map id. */
    public function select(string $authType, string $authId, string $panelId, int $mapId): int;
}
