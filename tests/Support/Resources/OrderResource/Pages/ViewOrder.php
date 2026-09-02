<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource\Pages;

use Aristonis\FilamentShortcutKeys\Tests\Support\Resources\OrderResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;
}
