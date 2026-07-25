<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class AuthoringDeniedException extends ShortcutKeysException
{
    public static function forPanel(string $panelId): self
    {
        return new self(
            "System-map authoring is not permitted for panel: {$panelId}",
            10005,
            'AUTHORING_DENIED',
        );
    }
}
