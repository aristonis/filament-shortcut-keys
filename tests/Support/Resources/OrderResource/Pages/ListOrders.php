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
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('export'),
        ];
    }
}
