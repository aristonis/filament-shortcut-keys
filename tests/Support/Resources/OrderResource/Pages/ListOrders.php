<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * Both header shapes on one page: Create picks up the resource's create-page url and renders as a
     * link, while `export` has no url and renders as a Livewire button. The adapter has to key both.
     * `ping` runs the moment it is clicked, with no modal, which is the shape that gets no default key.
     * `secret`, `gated` and `locked` are never clickable (hidden, hidden by a closure, disabled), so they
     * must not take a letter from the actions that are.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export')->requiresConfirmation(),
            Action::make('ping'),
            Action::make('secret')->requiresConfirmation()->visible(false),
            Action::make('gated')->requiresConfirmation()->visible(fn (): bool => false),
            Action::make('locked')->requiresConfirmation()->disabled(),
        ];
    }
}
