<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** Global shortcuts: one per page action, keyed on a letter from the action name. */
final class GlobalSet implements ShortcutSet
{
    /** @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention */
    public function __construct(private ?ModifierScheme $modifier = null) {}

    public function key(): string
    {
        return 'global';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::none();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        // On bare keys, an action that runs on the click itself starts switched off, since one stray
        // keystroke would run it; a developer who wants it keyed pins a letter in the overlay. A held
        // modifier or a leader is deliberate, so it keeps every action.
        $bare = $this->defaultModifier()->toString() === '';

        return array_map(fn (ActionTarget $target) => new ShortcutBinding(
            new ShortcutTarget('global', $target->name),
            null,
            ! ($bare && $target->runsImmediately),
            letterHint: $target->name,
        ), $pageContextProvider->actions());
    }

    public function clientHandler(): string
    {
        return 'global';
    }
}
