<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;

/**
 * Turns a panel's shortcut sets into a ResolvedMap for one page render. Implemented by the plain
 * ShortcutResolver and by any caching wrapper, so callers can depend on either interchangeably.
 */
interface Resolver
{
    public function resolve(
        NavigationProvider $nav,
        PageContextProvider $page,
        string $panelId,
        string $authType = '',
        string $authId = ''
    ): ResolvedMap;
}
