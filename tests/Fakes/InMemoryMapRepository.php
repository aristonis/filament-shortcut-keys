<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapRepository;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\MapData;

final class InMemoryMapRepository implements MapRepository
{
    public function __construct(private MapData $map) {}

    public function activeMap(string $authType, string $authId, string $panelId): MapData
    {
        return $this->map;
    }
}
