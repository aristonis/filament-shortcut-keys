<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

final readonly class ActionTarget
{
    public function __construct(
        public string $name,
        public string $label,
        public string $selector
    ) {}
}
