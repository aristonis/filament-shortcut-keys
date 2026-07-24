<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources;

use Aristonis\FilamentShortcutKeys\Tests\Support\Models\User;
use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource\Pages\ListUsers;
use Filament\Resources\Resource;

/**
 * Minimal test-only resource. Navigation sort 1 places it before OrderResource so adapters
 * that read the panel's navigation order have a stable, asserted ordering to reproduce.
 */
final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
