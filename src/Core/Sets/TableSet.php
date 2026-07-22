<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** Table shortcuts: fixed bare keys (search, row/page movement, row actions), active only when the page has a table. */
final class TableSet implements ShortcutSet
{
    public function key(): string
    {
        return 'table';
    }

    public function defaultModifier(): ModifierScheme
    {
        return ModifierScheme::none();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        if (! $pageContextProvider->hasTable()) {
            return [];
        }

        $reserved = [
            'search' => 'Slash',
            'row-up' => 'ArrowUp',
            'row-down' => 'ArrowDown',
            'select' => 'Space',
            'edit' => 'Enter',
            'delete' => 'Delete',
            'page-prev' => 'ArrowLeft',
            'page-next' => 'ArrowRight',
        ];

        $bindings = [];
        foreach ($reserved as $structureKey => $code) {
            $bindings[] = new ShortcutBinding(
                new ShortcutTarget('table', $structureKey),
                new KeyCombo(ModifierScheme::none(), $code),
            );
        }

        return $bindings;
    }

    public function clientHandler(): string
    {
        return 'table';
    }
}
