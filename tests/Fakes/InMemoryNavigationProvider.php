<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;

class InMemoryNavigationProvider implements NavigationProvider
{
    public function __construct(private array $items, private string $versionToken) {}

    public function items(string $panelId): array
    {
        return $this->items;
    }

    public function versionToken(string $panelId): string
    {
        return $this->versionToken;
    }
}
