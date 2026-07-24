<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\AcceptsCustomBindings;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/**
 * User-defined bindings. Nothing is discovered from the panel; the resolver adds them from the active
 * map. Registered so the map has a modifier scheme and client handler for them. Shares navigation's
 * Alt+Shift scheme so the two land in one clash pool and never collide on a letter.
 */
final class CustomSet implements AcceptsCustomBindings, ShortcutSet
{
    public function key(): string
    {
        return 'custom';
    }

    public function defaultModifier(): ModifierScheme
    {
        return ModifierScheme::altShift();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        return [];
    }

    public function clientHandler(): string
    {
        return 'custom';
    }
}
