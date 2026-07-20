<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;

final readonly class MapData
{
    public function __construct(
        public MapType $type,
        public ?array $modifiers,
        public array $entries,
        public int $version,
    ) {}
}
