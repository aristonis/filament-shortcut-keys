<?php

namespace Aristonis\FilamentShortcutKeys\Core\Sets;

use Aristonis\FilamentShortcutKeys\Core\Contracts\NavigationProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;
use Aristonis\FilamentShortcutKeys\Core\Contracts\ShortcutSet;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\KeyCombo;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ModifierScheme;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutBinding;
use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ShortcutTarget;

/** Table shortcuts: a fixed key per behaviour (search, row/page movement, row actions), active only when the page has a table. */
final class TableSet implements ShortcutSet
{
    /**
     * Built-in row actions this set binds to fixed keys (edit=Enter, delete=Delete). The custom
     * RowActionSet excludes these names so a dev can't auto-letter a key that's already reserved.
     */
    public const RESERVED_RECORD_ACTIONS = ['edit', 'delete'];

    /** Every behavior this set binds, and the physical key it binds it to in a left-to-right panel. */
    private const CODES = [
        'search' => 'Slash',
        'row-up' => 'ArrowUp',
        'row-down' => 'ArrowDown',
        'select' => 'Space',
        'edit' => 'Enter',
        'delete' => 'Delete',
        'page-prev' => 'ArrowLeft',
        'page-next' => 'ArrowRight',
    ];

    /**
     * @param  bool  $rightToLeft  mirrors the pagination arrows, matching a right-to-left panel where
     *                             Filament already flips the previous/next buttons and their icons.
     * @param  ModifierScheme|null  $modifier  a developer-configured scheme; null keeps the convention
     */
    public function __construct(private bool $rightToLeft = false, private ?ModifierScheme $modifier = null) {}

    /** @return string[] the behaviors this set always binds, whatever the panel direction */
    public static function behaviors(): array
    {
        return array_keys(self::CODES);
    }

    public function key(): string
    {
        return 'table';
    }

    public function defaultModifier(): ModifierScheme
    {
        return $this->modifier ?? ModifierScheme::none();
    }

    public function discover(NavigationProvider $navigationProvider, PageContextProvider $pageContextProvider, string $panelId): array
    {
        if (! $pageContextProvider->hasTable()) {
            return [];
        }

        $reserved = self::CODES;

        if ($this->rightToLeft) {
            [$reserved['page-prev'], $reserved['page-next']] = [$reserved['page-next'], $reserved['page-prev']];
        }

        $bindings = [];
        foreach ($reserved as $structureKey => $code) {
            $bindings[] = new ShortcutBinding(
                new ShortcutTarget('table', $structureKey),
                new KeyCombo($this->defaultModifier(), $code),
            );
        }

        return $bindings;
    }

    public function clientHandler(): string
    {
        return 'table';
    }
}
