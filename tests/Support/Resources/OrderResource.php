<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources;

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\Order;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\CreateOrder;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\EditOrder;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ListOrders;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages\ViewOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Minimal test-only resource with a table and two named row actions (`approve`, `reject`).
 * The same harness is reused by later units (page context + row actions), so the table and
 * record actions are declared now even though the navigation test only reads statics.
 *
 * It also carries the controls the table set binds to fixed keys — a searchable column, edit and
 * delete record actions, and record selection — so a browser run can prove those keys reach a real
 * Filament control instead of a no-op. UserResource deliberately stays column-only; the contrast
 * between the two is what proves the table keys come from the plugin.
 *
 * All four pages are registered on purpose. Filament gives View, Edit and Create a default url when
 * the matching page exists (Resources\Pages\Page::getDefaultActionUrl), which renders them as links
 * rather than as Livewire buttons. That is what `make:filament-resource` produces, so it is the shape
 * the adapters have to survive; an index-only fixture would only ever exercise the button shape.
 */
final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationLabel = 'Orders';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
            ])
            ->selectable()
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                Action::make('approve'),
                Action::make('reject'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
