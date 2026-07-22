<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

/** Stable identity of a shortcut — "set:structureKey" (e.g. navigation:products) — so overrides survive renames. */
final readonly class ShortcutTarget
{
    public function __construct(
        public string $set,
        public string $structureKey
    ) {}

    public function identity(): string
    {
        return "$this->set:$this->structureKey";
    }

    public function equals(self $other): bool
    {
        return $this->identity() === $other->identity();
    }
}
