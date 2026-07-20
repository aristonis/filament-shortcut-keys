<?php

namespace Aristonis\FilamentShortcutKeys\Core\Contracts;

use Aristonis\FilamentShortcutKeys\Core\ValueObjects\ActionTarget;

interface PageContextProvider
{
    /** @return ActionTarget[] on the current page */
    public function actions(): array;

    public function hasTable(): bool;
}
