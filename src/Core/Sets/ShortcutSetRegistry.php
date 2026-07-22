<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;

/** Holds the registered shortcut sets and gathers every set's raw bindings for a page. */
final class ShortcutSetRegistry
{
    private array $sets = [];

    public function register(ShortcutSet $set): void
    {
        $this->sets[$set->key()] = $set;
    }

    public function get(string $key): ?ShortcutSet
    {
        return $this->sets[$key] ?? null;
    }

    public function all(): array
    {
        return array_values($this->sets);
    }

    public function discover(NavigationProvider $nav, PageContextProvider $page, string $panelId): array
    {
        $bindings = [];
        foreach ($this->sets as $set) {
            $bindings = array_merge($bindings, $set->discover($nav, $page, $panelId));
        }

        return $bindings;
    }
}
