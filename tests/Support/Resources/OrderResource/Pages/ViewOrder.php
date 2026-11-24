<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * `archive` wants the A that the registered `approve` row action holds. `print` and `share` open
     * their url in a new tab, set the two ways Filament allows.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('archive')->requiresConfirmation(),
            Action::make('print')->url('/print/order', shouldOpenInNewTab: true),
            Action::make('share')->url('/share/order')->openUrlInNewTab(),
        ];
    }
}
