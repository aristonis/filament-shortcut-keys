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

    public static function forSharedLeader(string $code): self
    {
        return new self(
            "The leader key {$code} is used by both a panel-wide set (navigation, custom) and a page set (global, table, row-action, page). They are lettered separately, so their keys could collide. Give the page set a different leader.",
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
