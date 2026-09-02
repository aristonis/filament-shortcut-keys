<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;
use Filament\Actions\Action;
use Filament\Tables\Contracts\HasTable;

final class FilamentPageContextProvider implements PageContextProvider
{
    /** @param object $page the currently-rendering Filament page (a Livewire component) */
    public function __construct(private object $page) {}

    public function actions(): array
    {
        return $this->toTargets($this->page->getCachedHeaderActions(), fn (Action $action): ActionTarget => $this->toHeaderTarget($action));
    }

    public function hasTable(): bool
    {
        return $this->page instanceof HasTable;
    }

    public function rowActions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->toTargets($this->page->getTable()->getFlatRecordActions(), fn (Action $action): ActionTarget => $this->toRowTarget($action));
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
            '[wire\\:key*=".actions.' . $action->getName() . '."]',
        );
    }

    private function livewireSelector(Action $action): string
    {
        return '[wire\\:click^="mountAction(\'' . $action->getName() . '\'"]';
    }
}
