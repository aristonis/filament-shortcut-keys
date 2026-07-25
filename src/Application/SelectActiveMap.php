<?php

namespace Aristonis\FilamentShortcutKeys\Application;

use Aristonis\FilamentShortcutKeys\Core\Contracts\MapSelector;
use Aristonis\FilamentShortcutKeys\Exceptions\EditingLockedException;

/**
 * Lets an admin choose which map is active for a panel. Only allowed in `personal` mode; a `locked`
 * panel forces everyone onto the shared default, so there is nothing to choose.
 */
final readonly class SelectActiveMap
{
    private const PERSONAL = 'personal';

    public function __construct(
        private MapSelector $selector,
        private string $mode,
    ) {}

    public function select(string $authType, string $authId, string $panelId, int $mapId): int
    {
        if ($this->mode !== self::PERSONAL) {
            throw EditingLockedException::forPanel($panelId);
        }

        return $this->selector->select($authType, $authId, $panelId, $mapId);
    }
}
