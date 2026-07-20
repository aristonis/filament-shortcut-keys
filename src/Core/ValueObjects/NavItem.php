<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

final readonly class NavItem
{
    public function __construct(
        public string $structureKey,
        public string $label,
        public string $url,
    ) {}
}
