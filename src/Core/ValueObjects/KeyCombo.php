<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

/**
 * Immutable keyboard combo: a modifier scheme + one physical key code ("KeyP", "Slash").
 * The key is stored as e.code so matching is stable across keyboard layouts and RTL.
 */
final readonly class KeyCombo
{
    public function __construct(
        public ModifierScheme $modifiers,
        public string $code,
    ) {}
}
