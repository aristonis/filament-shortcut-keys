<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\NavItem;

interface NavigationProvider
{
    /** @return NavItem[] in sidebar order, already authorization-filtered */
    public function items(string $panelId): array;

    public function versionToken(string $panelId): string;
}
