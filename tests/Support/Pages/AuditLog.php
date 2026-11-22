<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Pages;

use Filament\Pages\Page;

/** In the sidebar only for the admin with id 1, as apps do when they hide pages per role. */
final class AuditLog extends Page
{
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->id() === 1;
    }
}
