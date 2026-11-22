<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Pages;

use Filament\Pages\Page;

/** A hidden page whose route needs a parameter, so it has no URL a key could open. */
final class DailyUsage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'daily-usage/{day}';
}
