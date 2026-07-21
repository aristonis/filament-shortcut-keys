<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;

interface ShortcutSet
{
    public function key(): string;

    public function defaultModifier(): ModifierScheme;

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array;

    public function clientHandler(): string;
}
