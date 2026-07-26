<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\AcceptsCustomBindings;
use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

/**
 * User-defined bindings. Nothing is discovered from the panel; the resolver adds them from the active
 * map. Registered so the map has a modifier scheme and client handler for them. Defaults to the same
 * scheme as navigation, which puts both in one clash pool so a custom key can never shadow a nav one.
 */
final class CustomSet implements AcceptsCustomBindings, ShortcutSet
{
    /** @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention */
    public function __construct(private ?ModifierScheme $modifier = null) {}

    public function key(): string
    {
        return 'custom';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::altShift();
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
