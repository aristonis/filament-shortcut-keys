<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\Resolution\LetterAssigner;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/**
 * Dev-registered custom row actions (e.g. approve/reject), auto-lettered and uniform across every
 * table. A shortcut is emitted only when the current page's table exposes a record action of that
 * name, so the reference overlay never shows a key the page can't use.
 */
final class RowActionSet implements ShortcutSet
{
    /** @param string[] $registeredNames dev-registered custom row-action names */
    public function __construct(private array $registeredNames) {}

    public function key(): string
    {
        return 'row-action';
    }

    public function defaultModifier(): ModifierScheme
    {
        return ModifierScheme::none();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        $names = array_values(array_diff($this->registeredNames, TableSet::RESERVED_RECORD_ACTIONS));

        $bindings = array_map(
            fn (string $name) => new ShortcutBinding(
                new ShortcutTarget('row-action', $name),
                null,
                letterHint: $name,
            ),
            $names,
        );

        // Letters are assigned over the FULL registered list, then filtered to what the page has,
        // so a name keeps the same key whether or not its siblings are present on a given page.
        $assigned = (new LetterAssigner)->assign(ModifierScheme::none(), $bindings);

        $present = array_map(fn (ActionTarget $action) => $action->name, $pageContextProvider->rowActions());

        return array_values(array_filter(
            $assigned,
            fn (ShortcutBinding $binding) => in_array($binding->target->structureKey, $present, true),
        ));
    }

    public function clientHandler(): string
    {
        return 'table';
    }
}
