<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapSummary;

/** Lists the maps an owner may pick from for a panel: the shared system presets plus their own custom map. */
interface ListMaps
{
    /** @return MapSummary[] */
    public function available(string $authType, string $authId, string $panelId): array;
}
