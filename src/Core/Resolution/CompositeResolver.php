<?php

namespace Aristonis\FilamentShortcutKeys\Core\Resolution;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\Resolver;

/**
 * Combines two resolvers into one map for a page render: the panel-wide resolver (navigation, cached
 * across pages) and the page resolver (the sets specific to the current page, resolved fresh). Both
 * see the same request, and their disjoint groups are concatenated into a single ResolvedMap.
 */
final readonly class CompositeResolver implements Resolver
{
    public function __construct(
        private Resolver $panelWide,
        private Resolver $page,
    ) {}

    public function resolve(
        NavigationProvider $nav,
        PageContextProvider $page,
        string $panelId,
        string $authType = '',
        string $authId = ''
    ): ResolvedMap {
        return $this->panelWide->resolve($nav, $page, $panelId, $authType, $authId)
            ->merge($this->page->resolve($nav, $page, $panelId, $authType, $authId));
    }
}
