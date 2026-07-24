<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\UserResource;
use Filament\Resources\Pages\ListRecords;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;
}
