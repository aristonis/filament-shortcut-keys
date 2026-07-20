<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;

interface MapRepository
{
    public function activeMap(string $authType, string $authId, string $panelId): MapData;
}
