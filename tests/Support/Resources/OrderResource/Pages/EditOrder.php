<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Resources\Pages\EditRecord;

final class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;
}
