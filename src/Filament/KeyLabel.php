<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/** Turns a modifier scheme + physical key code into display tokens (e.g. ['Alt', 'Shift', 'O']). */
final class KeyLabel
{
    private const MODIFIER_LABELS = ['ctrl' => 'Ctrl', 'alt' => 'Alt', 'shift' => 'Shift', 'meta' => 'Cmd'];

    private const CODE_LABELS = [
        'Slash' => '/',
        'ArrowUp' => '↑',
        'ArrowDown' => '↓',
        'ArrowLeft' => '←',
        'ArrowRight' => '→',
        'Enter' => 'Enter',
        'Space' => 'Space',
        'Delete' => 'Del',
        'Backspace' => 'Backspace',
    ];

    /** @return string[] */
    public static function tokens(ModifierScheme $modifier, string $code): array
    {
        $tokens = [];

        foreach (['ctrl', 'alt', 'shift', 'meta'] as $name) {
            if ($modifier->{$name}) {
                $tokens[] = self::MODIFIER_LABELS[$name];
            }
        }

        $tokens[] = self::CODE_LABELS[$code] ?? (str_starts_with($code, 'Key') ? substr($code, 3) : $code);

        return $tokens;
    }
}
