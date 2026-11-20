<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A table under the order's view and edit pages. It is its own Livewire component, so the page that
 * renders it has no table of its own — the shape that has to get table keys anyway. Only `approve`
 * of the registered row actions is here; `reject` is not.
 */
final class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    /** How many times the access check ran, so a test can see the keymap does not repeat it. */
    public static int $accessChecks = 0;

    /** Lets a test take the relation manager off the page, as a policy would. */
    public static bool $shown = true;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        self::$accessChecks++;

        return self::$shown && parent::canViewForRecord($ownerRecord, $pageClass);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
            ])
            ->recordActions([
                DeleteAction::make(),
                Action::make('approve'),
            ]);
    }
}
