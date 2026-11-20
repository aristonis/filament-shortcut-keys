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
 * name, so the cheatsheet never shows a key the page can't use. A page with a nested table
 * gets every registered name, and the cheatsheet hides the ones its tables turn out not to have.
 */
final class RowActionSet implements ShortcutSet
{
    /**
     * @param  string[]  $registeredNames  dev-registered custom row-action names
     * @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention
     * @param  string[]  $unavailableCodes  keys reserved in this set's pool, so no row action is lettered onto them
     */
    public function __construct(
        private array $registeredNames,
        private ?ModifierScheme $modifier = null,
        private array $unavailableCodes = [],
    ) {}

    public function key(): string
    {
        return 'row-action';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::none();
    }

    /**
     * The key each registered name takes, before any overlay pin. The same on every page, which is
     * what lets the header actions sharing this pool keep clear of all of them, not only of the ones
     * the current page has.
     *
     * @return array<string, string> name => key code
     */
    public function codes(): array
    {
        $codes = [];
        foreach ($this->lettered() as $binding) {
            $codes[$binding->target->structureKey] = (string) $binding->keyCombo?->code;
        }

        return $codes;
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        $assigned = $this->lettered();

        // A nested table (relation manager, table widget) loads after the page, so its actions are
        // unknown here: every registered name is kept and the cheatsheet checks the page for each.
        if ($pageContextProvider->hasNestedTables()) {
            return $assigned;
        }

        $present = array_map(fn (ActionTarget $action) => $action->name, $pageContextProvider->rowActions());

        return array_values(array_filter(
            $assigned,
            fn (ShortcutBinding $binding) => in_array($binding->target->structureKey, $present, true),
        ));
    }

    /**
     * Letters are assigned over the FULL registered list, then filtered to what the page has, so a
     * name keeps the same key whether or not its siblings are present on a given page.
     *
     * @return ShortcutBinding[]
     */
    private function lettered(): array
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

        return (new LetterAssigner)->assign($this->defaultModifier(), $bindings, $this->unavailableCodes);
    }

    public function clientHandler(): string
    {
        return 'table';
    }
}
