<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/**
 * Turns a modifier scheme + physical key code into display tokens: ['Alt', 'Shift', 'O'] for a combo,
 * ['g', 'o'] for a leader sequence. isSequence() tells the two apart, since both are a list of keys.
 */
final class KeyLabel
{
    private const MODIFIER_LABELS = ['ctrl' => 'Ctrl', 'alt' => 'Alt', 'shift' => 'Shift', 'meta' => 'Cmd'];

    private const CODE_LABELS = [
        'Slash' => '/',
        'ArrowUp' => '↑',
        'ArrowDown' => '↓',
        'ArrowLeft' => '←',
        'ArrowRight' => '→',
        'Delete' => 'Del',
        'Escape' => 'Esc',
    ];

    /** @return string[] */
    public static function tokens(ModifierScheme $modifier, string $code): array
    {
        // A sequence reads in lower case, as typed: "G then O" invites Shift+G, which is a different key.
        if ($modifier->leader !== null) {
            return [strtolower(self::keyName($modifier->leader)), strtolower(self::keyName($code))];
        }

        $tokens = [];

        foreach (['ctrl', 'alt', 'shift', 'meta'] as $name) {
            if ($modifier->{$name}) {
                $tokens[] = self::MODIFIER_LABELS[$name];
            }
        }

        $tokens[] = self::keyName($code);

        return $tokens;
    }

    /** Whether the keys are pressed one after the other rather than together. */
    public static function isSequence(ModifierScheme $modifier): bool
    {
        return $modifier->leader !== null;
    }

    private static function keyName(string $code): string
    {
        return self::CODE_LABELS[$code] ?? (str_starts_with($code, 'Key') ? substr($code, 3) : $code);
    }
}
