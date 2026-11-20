<?php

namespace Aristonis\FilamentShortcutKeys\Filament;

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;

/**
 * A table with no page around it, used to resolve the table keys and the row-action letters for the
 * reference page. Both are the same on every table in the panel. The table's own actions are unknown,
 * so the row-action set keeps every registered name, as it does for a nested table.
 */
final class AnyTablePageContextProvider implements PageContextProvider
{
    public function actions(): array
    {
        return [];
    }

    public function hasTable(): bool
    {
        return true;
    }

    public function hasNestedTables(): bool
    {
        return true;
    }

    public function rowActions(): array
    {
        return [];
    }
}
