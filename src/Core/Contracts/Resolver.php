<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;

/**
 * Turns a panel's shortcut sets into a ResolvedMap for one page render. Implemented by the plain
 * ShortcutResolver and by any caching wrapper, so callers can depend on either interchangeably.
 *
 * @internal Part of the resolution pipeline, not an extension point: the plugin builds its own sets
 *           and providers, so this may gain methods in a minor release.
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
