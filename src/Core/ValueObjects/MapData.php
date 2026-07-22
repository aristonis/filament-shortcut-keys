<?php

namespace Aristonis\FilamentShortcutKeys\Core\ValueObjects;

use Aristonis\FilamentShortcutKeys\Core\Enums\MapType;

/** A stored shortcut map: its type, optional per-set modifier overrides, override entries, and a version for cache-busting. */
final readonly class MapData
{
    public function __construct(
        public MapType $type,
        public ?array $modifiers,
        public array $entries,
        public int $version,
    ) {}
}
