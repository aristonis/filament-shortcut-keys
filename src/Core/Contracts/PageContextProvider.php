<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;

interface PageContextProvider
{
    /** @return ActionTarget[] the current page's header/page actions */
    public function actions(): array;

    public function hasTable(): bool;

    /** @return ActionTarget[] the current table's per-row (record) actions; empty when the page has no table */
    public function rowActions(): array;
}
