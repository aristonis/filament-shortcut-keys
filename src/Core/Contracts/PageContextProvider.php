<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;

interface PageContextProvider
{
    /** @return ActionTarget[] the current page's header/page actions */
    public function actions(): array;

    /** Whether any table renders on the page: its own, or one nested in it (see hasNestedTables) */
    public function hasTable(): bool;

    /**
     * Whether the page renders a table that belongs to a nested component, such as a relation manager
     * or a table widget. Those load on their own, so their row actions are not in rowActions().
     */
    public function hasNestedTables(): bool;

    /** @return ActionTarget[] the page's own table's per-row (record) actions; empty when the page has no table of its own */
    public function rowActions(): array;
}
