<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;

/** Records the selections a SelectActiveMap issues, so a unit test can assert on them without a database. */
final class InMemoryMapSelector implements MapSelector
{
    /** @var array<int, array{authType: string, authId: string, panelId: string, mapId: int}> */
    public array $selected = [];

    public function select(string $authType, string $authId, string $panelId, int $mapId): int
    {
        $this->selected[] = compact('authType', 'authId', 'panelId', 'mapId');

        return $mapId;
    }
}
