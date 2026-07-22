<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;

/** Loads a user's active shortcut map — their saved overrides — for a panel. */
interface MapRepository
{
    public function activeMap(string $authType, string $authId, string $panelId): MapData;
}
