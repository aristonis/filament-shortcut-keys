<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class InvalidCustomBindingException extends ShortcutKeysException
{
    public static function forReason(string $reason): self
    {
        return new self(
            "Invalid custom binding: {$reason}",
            10004,
            'INVALID_CUSTOM_BINDING',
        );
    }
}
