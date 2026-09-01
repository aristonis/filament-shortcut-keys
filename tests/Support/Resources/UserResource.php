<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources;

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource\Pages\ListUsers;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Minimal test-only resource. Navigation sort 1 places it before OrderResource so adapters
 * that read the panel's navigation order have a stable, asserted ordering to reproduce.
 *
 * Its table carries no row actions, unlike OrderResource, so tests can prove the table keys are
 * identical on an unrelated resource that shares no code with it.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
