<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/**
 * One category of shortcuts (navigation, global, table, page, or a third-party set). A set names
 * itself, declares its default modifier, discovers its raw bindings for a page, and names the client
 * handler that fires them. Register a new set to extend the keymap without touching the resolver.
 */
interface ShortcutSet
{
    public function key(): string;

    public function defaultModifier(): ModifierScheme;

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array;

    public function clientHandler(): string;
}
