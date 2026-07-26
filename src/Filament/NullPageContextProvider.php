<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;

/**
 * A page context with nothing in it, used when resolving the panel-wide map away from any live page
 * (the reference page). The navigation and custom sets need no page context, so the page-scoped sets
 * discover nothing.
 */
final class NullPageContextProvider implements PageContextProvider
{
    public function actions(): array
    {
        return [];
    }

    public function hasTable(): bool
    {
        return false;
    }

    public function rowActions(): array
    {
        return [];
    }
}
