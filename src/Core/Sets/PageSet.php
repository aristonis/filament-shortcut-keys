<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/** Page-scoped shortcuts declared by an individual page. Registered for precedence; discovery returns none until a page can declare them. */
final class PageSet implements ShortcutSet
{
    public function key(): string
    {
        return 'page';
    }

    public function defaultModifier(): ModifierScheme
    {
        return ModifierScheme::none();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        return [];
    }

    public function clientHandler(): string
    {
        return 'page';
    }
}
