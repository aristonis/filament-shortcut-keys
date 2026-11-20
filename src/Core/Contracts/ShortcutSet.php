<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/**
 * One category of shortcuts (navigation, global, table, row action, custom, page). A set names
 * itself, declares its default modifier, discovers its raw bindings for a page, and names the client
 * handler that fires them. A new category is a new set in a registry, not a change to the resolver.
 *
 * @internal Part of the resolution pipeline, not an extension point: the plugin builds its own sets
 *           and providers, so this may gain methods in a minor release.
 */
interface ShortcutSet
{
    public function key(): string;

    public function defaultModifier(): ModifierScheme;

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array;

    public function clientHandler(): string;
}
