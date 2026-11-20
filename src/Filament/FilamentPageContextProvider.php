<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;
use Filament\Widgets\WidgetConfiguration;

final class FilamentPageContextProvider implements PageContextProvider
{
    /**
     * The sets, the serializer and the cheatsheet all ask the same questions during one render, and
     * the answers run the app's visibility closures and relation manager policies, so each is worked
     * out once per provider.
     *
     * @var ActionTarget[]|null
     */
    private ?array $actions = null;

    /** @var ActionTarget[]|null */
    private ?array $rowActions = null;

    private ?bool $hasNestedTables = null;

    /** @param object $page the currently-rendering Filament page (a Livewire component) */
    public function __construct(private object $page) {}

    /**
     * Only header actions the user can click. A hidden action is not rendered and a disabled one does
     * nothing, so a key on either would be dead in the cheatsheet and would take a letter from the
     * bare pool that a live action could have had. Row actions are left alone: their visibility
     * depends on the record, which is not known until the cursor lands on a row.
     */
    public function actions(): array
    {
        if ($this->actions !== null) {
            return $this->actions;
        }

        $clickable = array_filter(
            $this->page->getCachedHeaderActions(),
            fn (object $action): bool => ! $action instanceof Action || ($action->isVisible() && ! $action->isDisabled()),
        );

        return $this->actions = $this->toTargets($clickable, fn (Action $action): ActionTarget => $this->toHeaderTarget($action));
    }

    public function hasTable(): bool
    {
        return $this->page instanceof HasTable || $this->hasNestedTables();
    }

    /**
     * A relation manager or a table widget is a Livewire component of its own, lazy by default, so
     * its table has not rendered when the page's keymap is built. All the page can know is that the
     * table is there.
     */
    public function hasNestedTables(): bool
    {
        return $this->hasNestedTables ??= $this->relationManagers() !== [] || $this->hasTableWidget();
    }

    public function rowActions(): array
    {
        if (! $this->page instanceof HasTable) {
            return [];
        }

        return $this->rowActions ??= $this->toTargets($this->page->getTable()->getFlatRecordActions(), fn (Action $action): ActionTarget => $this->toRowTarget($action));
    }

    /**
     * Map Filament actions to ActionTargets, skipping ActionGroups (their children are not
     * individually keyboard-bound yet).
     *
     * @param  array<Action|object>  $actions
     * @param  callable(Action): ActionTarget  $toTarget
     * @return ActionTarget[]
     */
    private function toTargets(array $actions, callable $toTarget): array
    {
        $flat = array_filter($actions, fn (object $action): bool => $action instanceof Action);

        return array_values(array_map($toTarget, $flat));
    }

    /**
     * A header action is page-scoped, so the server already knows everything about it.
     *
     * Filament gives Create, Edit and View a default url whenever the resource registers the matching
     * page, and an action with a url renders as a plain anchor with no wire:click — so keying on
     * wire:click alone leaves the most common header action on a resource unreachable. When a url is
     * there the client navigates it directly, which needs no DOM lookup at all; only a modal action
     * (no url) still has to be found and clicked.
     */
    private function toHeaderTarget(Action $action): ActionTarget
    {
        $url = $action->getUrl();

        return new ActionTarget(
            (string) $action->getName(),
            (string) $action->getLabel(),
            filled($url) ? null : $this->livewireSelector($action),
            filled($url) ? (string) $url : null,
            ! filled($url) && ! $this->asksFirst($action),
        );
    }

    /**
     * A row action is per-record, so its url differs for every row and the server has no business
     * choosing one — the client has to find the control inside whichever row the cursor is on.
     *
     * wire:key is the only hook Filament puts on a record action in both shapes: it is emitted for any
     * action bound to a record in a table, anchor or button alike. The record hash is appended, so the
     * match is on the `.actions.NAME.` segment rather than the whole value.
     */
    private function toRowTarget(Action $action): ActionTarget
    {
        return new ActionTarget(
            (string) $action->getName(),
            (string) $action->getLabel(),
            self::rowActionSelector((string) $action->getName()),
        );
    }

    /** The control for a named record action inside a table row, whichever component owns the table. */
    public static function rowActionSelector(string $name): string
    {
        return '[wire\\:key*=".actions.' . $name . '."]';
    }

    /**
     * The relation managers the record page shows, after Filament's own access checks. The cached
     * accessor is the one Filament renders from, so the policies behind it run once per request.
     *
     * @return array<mixed>
     */
    private function relationManagers(): array
    {
        return method_exists($this->page, 'getCachedRelationManagers') ? $this->page->getCachedRelationManagers() : [];
    }

    /**
     * Header and footer widgets on any page, plus the dashboard's own. Each accessor is probed rather
     * than assumed, so a page type that lacks one contributes nothing.
     *
     * Filament 5 marks getVisibleFooterWidgets() and getVisibleWidgets() deprecated in favour of
     * getWidgetsSchemaComponents(). When they go, a widget table loses its keys; the dashboard test
     * catches that. A table placed through a page's own content() schema is not seen here at all.
     */
    private function hasTableWidget(): bool
    {
        $widgets = [];
        foreach (['getVisibleHeaderWidgets', 'getVisibleFooterWidgets', 'getVisibleWidgets'] as $accessor) {
            if (method_exists($this->page, $accessor)) {
                $widgets = [...$widgets, ...$this->page->{$accessor}()];
            }
        }

        foreach ($widgets as $widget) {
            $class = $widget instanceof WidgetConfiguration ? $widget->widget : $widget;

            if (is_string($class) && is_subclass_of($class, HasTable::class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether clicking the action shows a confirmation or a modal before anything happens. Only the
     * signals Filament exposes without mounting the action count: an action whose only modal trigger
     * is a schema is not detected, so it is treated as running at once and gets no default key.
     */
    private function asksFirst(Action $action): bool
    {
        return $action->isConfirmationRequired() || $action->shouldOpenModal();
    }

    private function livewireSelector(Action $action): string
    {
        return '[wire\\:click^="mountAction(\'' . $action->getName() . '\'"]';
    }
}
