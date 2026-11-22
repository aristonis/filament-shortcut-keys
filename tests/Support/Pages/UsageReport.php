<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Support\Pages;

use Filament\Pages\Page;

/**
 * A drill-down page kept out of the sidebar, like the detail pages real panels reach only from a link.
 * It has no navigation sort and its label starts with the same letter as Users, so a key handed to it
 * would take the letter the admin expects for the visible Users item.
 */
final class UsageReport extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Usage report';
}
