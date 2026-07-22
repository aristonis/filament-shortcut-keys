<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\Resolution\ResolvedMap;

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
