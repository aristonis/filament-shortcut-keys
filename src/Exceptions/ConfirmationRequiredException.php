<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class ConfirmationRequiredException extends ShortcutKeysException
{
    public static function forSystemMap(int $mapId): self
    {
        return new self(
            "Editing shared system map {$mapId} in place requires explicit confirmation",
            10006,
            'CONFIRMATION_REQUIRED',
        );
    }
}
