<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class EditingLockedException extends ShortcutKeysException
{
    public static function forPanel(string $panelId): self
    {
        return new self(
            "Shortcut editing is disabled for panel: {$panelId}",
            10003,
            'EDITING_LOCKED',
        );
    }
}
