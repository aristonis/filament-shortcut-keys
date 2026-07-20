<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class InvalidKeyComboException extends ShortcutKeysException
{
    public static function forInput(string $input): self
    {
        return new self("Invalid key combo: {$input}", 10001, 'INVALID_KEY_COMBO');
    }
}
