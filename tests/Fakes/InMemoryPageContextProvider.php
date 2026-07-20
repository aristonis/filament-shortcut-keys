<?php

namespace Aristonis\FilamentShortcutKeys\Tests\Fakes;

use Aristonis\FilamentShortcutKeys\Core\Contracts\PageContextProvider;

class InMemoryPageContextProvider implements PageContextProvider
{
    public function __construct(private array $actions, private bool $hasTable) {}

    public function actions(): array
    {
        return $this->actions;
    }

    public function hasTable(): bool
    {
        return $this->hasTable;
    }
}
