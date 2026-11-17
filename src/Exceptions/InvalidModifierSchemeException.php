<?php

namespace Aristonis\FilamentShortcutKeys\Exceptions;

final class InvalidModifierSchemeException extends ShortcutKeysException
{
    public static function forToken(string $token): self
    {
        return new self(
            "Unknown modifier token: {$token}. Use any of ctrl, alt, shift, meta, or one letter for a leader key.",
            10008,
            'INVALID_MODIFIER_SCHEME',
        );
    }

    public static function forReservedLeader(string $code): self
    {
        return new self(
            "The leader key {$code} is already a bare shortcut of its own (a table key), so it cannot start a sequence. Pick another letter.",
            10008,
            'INVALID_MODIFIER_SCHEME',
        );
    }

    public static function forSharedPool(string $set, string $pool): self
    {
        $keys = $pool === '' ? 'bare keys' : $pool;

        return new self(
            "The {$set} set uses the same keys ({$keys}) as navigation or custom bindings. The two are lettered separately, so their shortcuts could collide. Give {$set} different modifiers or a leader of its own.",
            10008,
            'INVALID_MODIFIER_SCHEME',
        );
    }

    public static function forMixedLeader(array $tokens): self
    {
        return new self(
            'A leader key cannot be combined with other tokens: ' . implode(', ', $tokens) . '. Use one letter on its own.',
            10008,
            'INVALID_MODIFIER_SCHEME',
        );
    }
}
