<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

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
