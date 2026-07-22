<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class UnknownShortcutSetException extends ShortcutKeysException
{
    public static function forSet(string $set): self
    {
        return new self("No shortcut set registered for key: {$set}", 10002, 'UNKNOWN_SHORTCUT_SET');
    }
}
