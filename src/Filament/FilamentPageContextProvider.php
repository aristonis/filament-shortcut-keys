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
        return $this->toTargets($this->page->getCachedHeaderActions());
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

        return $this->toTargets($this->page->getTable()->getFlatRecordActions());
    }

    /**
     * Map Filament actions to ActionTargets, skipping ActionGroups (their children are not
     * individually keyboard-bound yet).
     *
     * @param  array<Action|object>  $actions
     * @return ActionTarget[]
     */
    private function toTargets(array $actions): array
    {
        $flat = array_filter($actions, fn (object $action): bool => $action instanceof Action);

        return array_values(array_map(fn (Action $action): ActionTarget => $this->toTarget($action), $flat));
    }

    private function toTarget(Action $action): ActionTarget
    {
        // Filament renders every action with wire:click="mountAction('NAME', ...)", so the
        // action name in that attribute is the stable hook the client uses to find the control.
        $selector = '[wire\\:click^="mountAction(\'' . $action->getName() . '\'"]';

        return new ActionTarget((string) $action->getName(), (string) $action->getLabel(), $selector);
    }
}
