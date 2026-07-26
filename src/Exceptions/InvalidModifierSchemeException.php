<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class InvalidModifierSchemeException extends ShortcutKeysException
{
    public static function forToken(string $token): self
    {
        return new self(
            "Unknown modifier token: {$token}. Use any of ctrl, alt, shift, meta.",
            10008,
            'INVALID_MODIFIER_SCHEME',
        );
    }
}
