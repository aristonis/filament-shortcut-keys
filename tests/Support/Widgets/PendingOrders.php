<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Widgets;

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** A table on the dashboard, which is a page with no table of its own. */
final class PendingOrders extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query())
            ->columns([
                TextColumn::make('name'),
            ])
            ->recordActions([
                Action::make('approve'),
            ]);
    }
}
