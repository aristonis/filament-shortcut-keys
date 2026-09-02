<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
