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
    /**
     * @param  string[]  $registeredNames  dev-registered custom row-action names
     * @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention
     * @param  ModifierScheme|null  $tableModifier  the table set's scheme, whose keys these letters must avoid
     */
    public function __construct(
        private array $registeredNames,
        private ?ModifierScheme $modifier = null,
        private ?ModifierScheme $tableModifier = null,
    ) {}

    public function key(): string
    {
        return 'row-action';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::none();
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
        $assigned = (new LetterAssigner)->assign($this->defaultModifier(), $bindings, $this->tableCodes());

        $present = array_map(fn (ActionTarget $action) => $action->name, $pageContextProvider->rowActions());

        return array_values(array_filter(
            $assigned,
            fn (ShortcutBinding $binding) => in_array($binding->target->structureKey, $present, true),
        ));
    }

    /**
     * The table's keys are off limits only when both sets use the same modifiers; Alt+J and a bare J
     * never meet, and reserving it there would shift letters for nothing.
     *
     * @return string[]
     */
    private function tableCodes(): array
    {
        $tableScheme = $this->tableModifier ?? ModifierScheme::none();

        return $this->defaultModifier()->equals($tableScheme) ? TableSet::reservedCodes() : [];
    }

    public function clientHandler(): string
    {
        return 'table';
    }
}
