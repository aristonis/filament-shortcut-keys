<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** Navigation shortcuts: one per registered nav item, keyed on a letter from its label. */
final class NavigationSet implements ShortcutSet
{
    /** @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention */
    public function __construct(private ?ModifierScheme $modifier = null) {}

    public function key(): string
    {
        return 'navigation';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::altShift();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        return array_map(
            fn (NavItem $item) => new ShortcutBinding(
                new ShortcutTarget('navigation', $item->structureKey),
                null,
                letterHint: $item->label,
            ),
            $navigationProvider->items($panelId)
        );
    }

    public function clientHandler(): string
    {
        return 'navigation';
    }
}
