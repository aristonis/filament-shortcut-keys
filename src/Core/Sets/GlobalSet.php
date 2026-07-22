<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** Global shortcuts: one per page action, Alt + a letter from the action name. */
final class GlobalSet implements ShortcutSet
{
    public function key(): string
    {
        return 'global';
    }

    public function defaultModifier(): ModifierScheme
    {
        return ModifierScheme::alt();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        return array_map(fn (ActionTarget $target) => new ShortcutBinding(
            new ShortcutTarget('global', $target->name),
            null,
            letterHint: $target->name,
        ), $pageContextProvider->actions());
    }

    public function clientHandler(): string
    {
        return 'global';
    }
}
